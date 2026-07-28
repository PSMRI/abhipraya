# Deployment architecture

## Delivery and release flow

![Abhipraya deployment architecture diagram](/ui/assets/img/docs/abhipraya-deployment-architecture.svg)

This is a provider-neutral deployment reference model. It shows a controlled path from source code to development, UAT, and production environments. Git hosting, CI/CD automation, container images, registries, and Kubernetes are optional choices; Abhipraya can also be deployed through a conventional controlled PHP release process to a web/application server.

## Deployment stages

| Stage | Purpose | Required controls |
| --- | --- | --- |
| Source control | Store and review application, configuration, migration, and documentation changes | Protected branches, peer review, no secrets in Git |
| Build and validation | Install dependencies, lint PHP, validate configuration, and run appropriate tests | Locked dependencies, repeatable scripts, reviewable build logs |
| Release package | Produce a versioned release archive or optional container image | Version tag, dependency inventory, licence review, integrity checks |
| Deployment approval | Promote an approved release through development, UAT, and production | Environment-specific approvals and rollback plan |
| Runtime deployment | Run the PHP application behind the selected web server/reverse proxy | HTTPS, restricted `.env`, database migrations, health checks |
| Operations | Observe, back up, and recover the service | Monitoring, security logs, backup/restore tests, incident process |

## Supported deployment approaches

### Conventional PHP deployment

The baseline approach copies an approved release to a protected PHP host, installs Composer dependencies, applies migrations, configures environment values outside the web root, validates HTTPS/routes, and reloads the application process. This is suitable for small and medium deployments.

### Optional container and orchestration deployment

Teams may package Abhipraya as a container image and deploy it through a private registry to an orchestration platform such as Kubernetes. In that model, the platform manages application replicas and rollout behaviour, while database credentials, configuration, backups, HTTPS, logging, and scope/security controls remain required.

Containerisation or Kubernetes should be adopted only when the operating team has the capacity to manage image security, secrets, deployment manifests, observability, upgrades, and recovery procedures.

## Release safeguards

- Do not commit `.env` files, production credentials, database dumps, or private keys.
- Use environment-specific configuration and separate database credentials for development, UAT, and production.
- Run database migrations in a controlled order and confirm backup/rollback readiness before production changes.
- Validate the public survey, administrator login, scope enforcement, QR generation, analytics, and CAPA workflows after deployment.
- Retain release version, deployment time, approver, migration record, and test evidence for auditability.

## Related documentation

- [Infrastructure architecture](infrastructure_architecture.md)
- [Technology architecture and open-source tools](technology_architecture.md)
- [Deployment guide](../deployment/deployment_guide.md)
- [Backup and restore](../deployment/backup_restore_guide.md)
- [Release checklist](../compliance/release_checklist.md)
