# Infrastructure architecture

## Secure logical deployment

![Abhipraya infrastructure architecture diagram](/ui/assets/img/docs/abhipraya-infrastructure-architecture.svg)

Abhipraya is deployed as a web application behind HTTPS. The diagram uses a cloud-style, scalable reference layout: DNS, a protected public edge, private application nodes, private data services, and operational storage. It is a logical deployment model, not a requirement for a particular cloud provider, operating system, container platform, or web server. A small deployment may place the web application and database on separate protected hosts; larger deployments can scale the web/application tier horizontally behind a load balancer.

## Infrastructure layers

| Layer | Responsibility | Key controls |
| --- | --- | --- |
| Client access | Public QR surveys and authenticated administration | HTTPS-only access, secure browser sessions, no direct database access |
| Edge and routing | DNS, TLS termination, reverse proxy, load balancer, or web server | TLS certificates, request-size limits, security headers, rate limits, and route rules |
| Application tier | PHP UI, API routes, services, authentication, analytics, and CAPA | Restricted environment configuration, server-side scope enforcement, CSRF, audit logging |
| Data tier | MySQL/MariaDB transactional database and JSON survey/configuration files | Private network access, least-privilege database user, encryption/backup policy |
| Operations tier | Backups, monitoring, logs, updates, and recovery procedures | Access-controlled administration, retention rules, restore testing, and incident response |

## Deployment principles

- Expose only HTTPS endpoints at the public edge. Keep the database and configuration files off the public network.
- Store `.env` credentials outside the public web root and restrict them to the application process identity.
- Allow database connections only from the application tier using a least-privilege account.
- Keep JSON survey packages and application code under controlled deployment and change-management processes.
- Back up database records and required configuration regularly; test restoration before relying on a backup policy.
- Monitor service availability, PHP/application errors, failed authentication activity, and database health without logging unnecessary personal information.
- Use IIS, Apache, Nginx, or another compatible web server/reverse proxy according to local hosting standards.

> **Deployment note:** Containers, orchestration platforms, managed load balancers, object storage, and managed database services are optional implementation choices. The core Abhipraya application can also run on conventional PHP hosts with a protected MySQL/MariaDB server.

## Scaling options

The first deployment can use one application host and one protected database host. When demand increases, add stateless PHP application nodes behind a load balancer. Keep session handling, uploads, scheduled jobs, and shared configuration under a deliberate scaling plan before adding nodes.

## Related documentation

- [Technology architecture and tools](technology_architecture.md)
- [Technical architecture overview](technical_architecture.md)
- [Service architecture and map](service_map.md)
- [Deployment guide](../deployment/deployment_guide.md)
- [Backup and restore](../deployment/backup_restore_guide.md)
- [Security guide](../security.md)
