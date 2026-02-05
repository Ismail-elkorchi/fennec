package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"
)

var version = "dev"

type Config struct {
	ControllerURL   string
	Token           string
	PollInterval    time.Duration
	HeartbeatPeriod time.Duration
}

type Client struct {
	baseURL           string
	token             string
	httpClient        *http.Client
	heartbeatInterval time.Duration
}

type Job struct {
	ID      int               `json:"id"`
	Type    string            `json:"type"`
	Payload map[string]any    `json:"payload"`
	Status  string            `json:"status"`
	Result  map[string]any    `json:"result"`
	Error   *string           `json:"last_error"`
	Meta    map[string]string `json:"-"`
}

type claimResponse struct {
	Job *Job `json:"job"`
}

type problemDetails struct {
	Type   string `json:"type"`
	Title  string `json:"title"`
	Status int    `json:"status"`
	Detail string `json:"detail"`
}

func main() {
	if len(os.Args) < 2 {
		fmt.Printf("Fennec agent %s\n", version)
		printUsage()
		os.Exit(2)
	}

	switch os.Args[1] {
	case "run-once":
		cfg, err := loadConfig()
		if err != nil {
			fatal(err)
		}
		if err := runOnce(context.Background(), cfg); err != nil {
			fatal(err)
		}
	case "work":
		cfg, err := loadConfig()
		if err != nil {
			fatal(err)
		}
		if err := runWork(context.Background(), cfg); err != nil {
			fatal(err)
		}
	case "version":
		fmt.Printf("Fennec agent %s\n", version)
	default:
		printUsage()
		os.Exit(2)
	}
}

func printUsage() {
	fmt.Println("Usage:")
	fmt.Println("  fennec-agent run-once")
	fmt.Println("  fennec-agent work")
	fmt.Println("  fennec-agent version")
}

func fatal(err error) {
	fmt.Fprintln(os.Stderr, err.Error())
	os.Exit(1)
}

func loadConfig() (Config, error) {
	controllerURL := strings.TrimRight(strings.TrimSpace(os.Getenv("FENNEC_CONTROLLER_URL")), "/")
	if controllerURL == "" {
		return Config{}, errors.New("FENNEC_CONTROLLER_URL is required")
	}

	token := strings.TrimSpace(os.Getenv("FENNEC_AGENT_TOKEN"))
	if token == "" {
		return Config{}, errors.New("FENNEC_AGENT_TOKEN is required")
	}

	heartbeatInterval, err := parseDurationEnv("FENNEC_HEARTBEAT_INTERVAL_SECONDS", 20*time.Second)
	if err != nil {
		return Config{}, err
	}

	pollInterval, err := parseDurationEnv("FENNEC_POLL_INTERVAL_MS", 1000*time.Millisecond)
	if err != nil {
		return Config{}, err
	}

	return Config{
		ControllerURL:   controllerURL,
		Token:           token,
		PollInterval:    pollInterval,
		HeartbeatPeriod: heartbeatInterval,
	}, nil
}

func parseDurationEnv(key string, fallback time.Duration) (time.Duration, error) {
	raw := strings.TrimSpace(os.Getenv(key))
	if raw == "" {
		return fallback, nil
	}

	value, err := strconv.Atoi(raw)
	if err != nil || value <= 0 {
		return 0, fmt.Errorf("%s must be a positive integer", key)
	}

	switch key {
	case "FENNEC_HEARTBEAT_INTERVAL_SECONDS":
		return time.Duration(value) * time.Second, nil
	case "FENNEC_POLL_INTERVAL_MS":
		return time.Duration(value) * time.Millisecond, nil
	default:
		return fallback, nil
	}
}

func runOnce(ctx context.Context, cfg Config) error {
	client := newClient(cfg)
	job, err := client.Claim(ctx)
	if err != nil {
		return err
	}
	if job == nil {
		return nil
	}

	heartbeatDone := make(chan struct{})
	heartbeatCtx, cancelHeartbeat := context.WithCancel(context.Background())
	go func() {
		defer close(heartbeatDone)
		client.heartbeatLoop(heartbeatCtx, job.ID)
	}()

	status, result, errMsg := executeJob(job)
	cancelHeartbeat()
	<-heartbeatDone

	return client.Complete(ctx, job.ID, status, result, errMsg)
}

func runWork(ctx context.Context, cfg Config) error {
	for {
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}

		err := runOnce(ctx, cfg)
		if err != nil {
			fmt.Fprintln(os.Stderr, err.Error())
		}
		time.Sleep(cfg.PollInterval)
	}
}

func executeJob(job *Job) (string, map[string]any, *string) {
	switch job.Type {
	case "noop":
		return "succeeded", map[string]any{"message": "noop"}, nil
	default:
		msg := fmt.Sprintf("unsupported job type: %s", job.Type)
		return "failed", map[string]any{}, &msg
	}
}

func newClient(cfg Config) *Client {
	return &Client{
		baseURL:           cfg.ControllerURL,
		token:             cfg.Token,
		httpClient:        &http.Client{Timeout: 15 * time.Second},
		heartbeatInterval: cfg.HeartbeatPeriod,
	}
}

func (c *Client) Claim(ctx context.Context) (*Job, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+"/agent/v1/jobs/claim", nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Accept", "application/json")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusNoContent {
		return nil, nil
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("claim failed: %s", formatProblem(body))
	}

	if len(body) == 0 {
		return nil, nil
	}

	var payload claimResponse
	if err := json.Unmarshal(body, &payload); err != nil {
		return nil, err
	}
	if payload.Job == nil || payload.Job.ID == 0 {
		return nil, errors.New("claim response missing job")
	}

	return payload.Job, nil
}

func (c *Client) Complete(ctx context.Context, jobID int, status string, result map[string]any, errMsg *string) error {
	payload := map[string]any{
		"status": status,
		"result": result,
	}
	if errMsg != nil {
		payload["error"] = *errMsg
	}

	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	req, err := http.NewRequestWithContext(
		ctx,
		http.MethodPost,
		fmt.Sprintf("%s/agent/v1/jobs/%d/complete", c.baseURL, jobID),
		bytes.NewReader(body),
	)
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return err
	}

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("complete failed: %s", formatProblem(respBody))
	}

	return nil
}

func (c *Client) Heartbeat(ctx context.Context, jobID int) error {
	req, err := http.NewRequestWithContext(
		ctx,
		http.MethodPost,
		fmt.Sprintf("%s/agent/v1/jobs/%d/heartbeat", c.baseURL, jobID),
		nil,
	)
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Accept", "application/json")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return err
	}

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("heartbeat failed: %s", formatProblem(body))
	}

	return nil
}

func (c *Client) heartbeatLoop(ctx context.Context, jobID int) {
	if err := c.Heartbeat(context.Background(), jobID); err != nil {
		fmt.Fprintln(os.Stderr, err.Error())
	}

	ticker := time.NewTicker(c.heartbeatInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			if err := c.Heartbeat(ctx, jobID); err != nil {
				fmt.Fprintln(os.Stderr, err.Error())
			}
		}
	}
}

func formatProblem(body []byte) string {
	if len(body) == 0 {
		return "empty response"
	}

	var problem problemDetails
	if err := json.Unmarshal(body, &problem); err == nil && problem.Title != "" {
		if problem.Detail != "" {
			return fmt.Sprintf("%s: %s", problem.Title, problem.Detail)
		}
		return problem.Title
	}

	return string(body)
}
