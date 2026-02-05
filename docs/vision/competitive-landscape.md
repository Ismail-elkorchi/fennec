# Competitive Landscape (2026+)

This document is a factual snapshot of common hosting control panels and adjacent tools.
It is meant to keep our strategy grounded and measurable.

## Panel comparisons (sources linked)

| Panel | Key strengths | Key gaps/opportunities | Source links |
| --- | --- | --- | --- |
| aaPanel | Broad hosting panel with a large plugin ecosystem and mainstream Linux server workflows. | Opportunity for stronger determinism, auditability, and multi-node orchestration. | [aaPanel](https://www.aapanel.com/) |
| CyberPanel | Tight integration with OpenLiteSpeed and a focused web hosting workflow. | Opportunity to provide broader stack choices and clearer atomic config rollout guarantees. | [CyberPanel](https://docs.openlitespeed.org/panel/cyberpanel/) |
| CloudPanel | Opinionated modern stack with a guided install and curated service set. | Opportunity to offer a multi-node story and container-aware workflows. | [CloudPanel overview](https://www.cloudpanel.io/blog/what-is-cloudpanel/), [Tech stack](https://www.cloudpanel.io/docs/v2/technology-stack/) |
| ISPConfig | Long-standing panel with a wide set of services and roles. | Opportunity to simplify workflows while adding stronger change auditing and rollback speed. | [ISPConfig](https://www.ispconfig.org/ispconfig/services-and-functions/) |
| Virtualmin | Mature feature set for multi-domain hosting and admin tasks. | Opportunity to reduce operational complexity and formalize safe, atomic changes. | [Virtualmin](https://www.virtualmin.com/) |
| Webmin | General-purpose system administration UI with wide surface area. | Opportunity to focus scope, guarantee idempotent operations, and harden the security model. | [Webmin](https://webmin.com/) |
| Hestia | Lightweight hosting panel focused on single-node workflows. | Opportunity for multi-node control, stronger audit trails, and plugin safety. | [Hestia](https://hestiacp.com/features) |

## Cross-panel opportunities for Fennec

- Deterministic operations and atomic config rollout.
- Strong audit logs with clear provenance for every change.
- A better multi-node story that scales beyond a single server.
- A plugin ecosystem that does not degrade security posture.
- Container-aware workflows, while noting CloudPanel explicitly states it does not support Linux containers. (See CloudPanel technology stack doc.)

## Notes

- This is not an endorsement list; it is a baseline for differentiation.
- We should refresh this snapshot quarterly as upstream docs and feature sets change.
