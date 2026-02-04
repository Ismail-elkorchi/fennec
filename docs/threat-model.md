# Threat Model (Initial)

Status: Draft
Date: 2026-02-04

## Assumptions
- The controller runs unprivileged and communicates with privileged agents.
- Agents enforce local safety policies and apply system changes.
- All API/UI/CLI access is authenticated and authorized.

## Assets
- Secrets (API keys, cert private keys, database passwords)
- Control plane state (projects, nodes, resources)
- Audit logs and job history

## Entry Points
- Public API endpoints
- UI front-end
- Agent registration and communication channel
- Migration tooling input files

## Trust Boundaries
- Controller <-> Agent (mutual TLS)
- User/Operator <-> Controller
- External services (DNS, mail, database providers)

## Threats to Consider
- Privilege escalation across the control plane / agent boundary
- Unauthorized access to secrets and state
- Configuration drift leading to service outages
- Supply-chain risk in plugins or providers

## Open Questions
- What is the minimum agent capability set in v1?
- Which secrets management approach will be used initially?
- What is the required audit log retention policy?
