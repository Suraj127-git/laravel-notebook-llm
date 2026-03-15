# K3s Infrastructure for NotebookLLM

This directory contains Kubernetes manifests and scripts for deploying the NotebookLLM application to a k3s cluster with separate configurations for local development and production environments.

## 📁 Directory Structure

```
infra/
├── k3s/                    # Kubernetes manifests
│   ├── local/              # Local development configuration
│   │   ├── namespace.yaml  # Local namespace
│   │   ├── postgres.yaml   # PostgreSQL with pgvector
│   │   ├── redis.yaml      # Redis cache and queue
│   │   ├── backend.yaml    # Laravel backend services
│   │   ├── frontend.yaml   # Vite frontend
│   │   └── ingress.yaml    # Local ingress routing
│   └── server/             # Production server configuration
│       ├── namespace.yaml  # Production namespace
│       ├── postgres.yaml   # PostgreSQL with secrets and resource limits
│       ├── redis.yaml      # Redis with authentication
│       ├── backend.yaml    # Laravel services with scaling
│       ├── frontend.yaml   # Frontend with scaling
│       ├── ingress.yaml    # Production ingress with TLS
│       └── hpa.yaml        # Horizontal Pod Autoscalers
├── scripts/                # Deployment scripts
│   ├── deploy.sh           # Deploy application (local|server)
│   ├── verify.sh           # Verify deployment status
│   └── teardown.sh         # Remove deployment
└── README.md               # This file
```

## 🚀 Quick Start

### Prerequisites

1. **k3s cluster** running and accessible via kubectl
2. **Docker** installed for building images
3. **kubectl** configured to access the k3s cluster

### Environment Selection

Choose between **local** (development) and **server** (production) environments:

#### Local Development
```bash
cd infra/scripts
./deploy.sh local
./verify.sh local
```

#### Production Server
```bash
cd infra/scripts
./deploy.sh server
./verify.sh server
```

### Access URLs

#### Local Environment
- Frontend: http://localhost/
- Backend API: http://localhost/api

#### Server Environment
- Frontend: https://yourdomain.com/
- Backend API: https://yourdomain.com/api

### Teardown

Remove deployments by environment:
```bash
./teardown.sh local    # Remove local deployment
./teardown.sh server   # Remove server deployment
```

## 📋 Kubernetes Components

### Database Layer
- **PostgreSQL**: StatefulSet with persistent storage
  - Local: 5Gi storage
  - Server: 50Gi storage with resource limits
- **Redis**: Deployment for caching and queues
  - Local: No authentication
  - Server: Password authentication

### Application Layer
- **Backend**: Laravel application with HTTP server
  - Local: 1 replica, debug enabled
  - Server: 3 replicas, production settings
- **Reverb**: WebSocket server for real-time features
  - Local: 1 replica
  - Server: 2 replicas
- **Queue Worker**: Background job processing
  - Local: 1 replica
  - Server: 2 replicas
- **Scheduler**: Laravel task scheduler
  - Local: 1 replica
  - Server: 1 replica

### Frontend Layer
- **Frontend**: Vite development server
  - Local: 1 replica
  - Server: 2 replicas

### Networking
- **Services**: ClusterIP services for internal communication
- **Ingress**: nginx ingress controller for external access
  - Local: HTTP only, localhost
  - Server: HTTPS with TLS, domain-based

### Autoscaling (Server Only)
- **HPA**: Horizontal Pod Autoscalers for backend and frontend
  - Backend: 3-10 replicas based on CPU/memory
  - Frontend: 2-5 replicas based on CPU/memory

## 🔧 Configuration Differences

### Local Environment
- Namespace: `notebookllm-local`
- Debug mode enabled
- No resource limits
- Smaller storage (5Gi)
- HTTP only
- ConfigMaps for all configuration
- No authentication on Redis

### Server Environment
- Namespace: `notebookllm-prod`
- Production mode, debug disabled
- Resource limits and requests
- Larger storage (50Gi)
- HTTPS with TLS
- Secrets for sensitive data
- Redis authentication
- Horizontal Pod Autoscaling
- Multiple replicas for high availability

## 🔧 Configuration

### Environment Variables

The application uses ConfigMaps and Secrets for configuration:

**Local ConfigMaps:**
- Database connection settings (plain text)
- Redis connection settings (plain text)
- Debug and development settings

**Server ConfigMaps & Secrets:**
- Non-sensitive configuration in ConfigMaps
- Sensitive data (passwords, keys) in Secrets
- Production-specific settings

### Storage

- **Local PostgreSQL**: 5Gi persistent volume (ReadWriteOnce)
- **Server PostgreSQL**: 50Gi persistent volume with fast-ssd storage class
- **Application**: No persistent storage (stateless containers)

## 🛠️ Server Setup Requirements

### Before deploying to server environment:

1. **Update domain names** in `k3s/server/ingress.yaml`
2. **Create secrets** with actual base64-encoded values:
   ```bash
   echo -n "your-app-key" | base64
   echo -n "your-db-password" | base64
   echo -n "your-redis-password" | base64
   ```
3. **Install cert-manager** for TLS certificate management
4. **Configure storage class** (update `fast-ssd` in postgres.yaml)
5. **Set up image registry** (if not using local Docker)

### Example Secret Creation:
```bash
kubectl create secret generic backend-secret \
  --from-literal=APP_KEY=base64-encoded-key \
  --from-literal=DB_PASSWORD=base64-encoded-db-password \
  --from-literal=REDIS_PASSWORD=base64-encoded-redis-password \
  -n notebookllm-prod
```

## 🛠️ Customization

### Image Registry

By default, the scripts use local Docker images. To use a registry:

1. Update image references in YAML files
2. Uncomment and modify the registry commands in `deploy.sh`

### Resource Limits

Add resource limits to deployments as needed:

```yaml
resources:
  requests:
    memory: "256Mi"
    cpu: "250m"
  limits:
    memory: "512Mi"
    cpu: "500m"
```

### Scaling

Modify replica counts in the YAML files:
- Backend: `spec.replicas`
- Frontend: `spec.replicas`

## 🔍 Troubleshooting

### Common Issues

1. **Pods not starting**: Check logs and events
   ```bash
   kubectl logs <pod-name> -n notebookllm
   kubectl describe pod <pod-name> -n notebookllm
   ```

2. **Service connectivity**: Verify endpoints
   ```bash
   kubectl get endpoints -n notebookllm
   ```

3. **Ingress issues**: Check ingress controller status
   ```bash
   kubectl get ingress -n notebookllm
   ```

### Debug Commands

```bash
# Get all resources
kubectl get all -n notebookllm

# Watch pod status
watch kubectl get pods -n notebookllm

# Port forward to services
kubectl port-forward svc/backend 8000:8000 -n notebookllm
kubectl port-forward svc/frontend 5173:5173 -n notebookllm
```

## 📊 Monitoring

The `verify.sh` script provides comprehensive deployment status including:
- Pod health and status
- Service endpoints
- Recent logs
- Access URLs

Run it regularly to monitor the deployment health.

## 🔐 Security Notes

- Default passwords are used for development
- Consider using Kubernetes Secrets for production
- Enable RBAC for production deployments
- Use HTTPS with proper certificates in production
