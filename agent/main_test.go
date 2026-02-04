package main

import "testing"

func TestVersionNotEmpty(t *testing.T) {
	if version == "" {
		t.Fatal("version must not be empty")
	}
}
