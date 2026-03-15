#!/bin/bash

set -e

# Parse command line arguments
ENVIRONMENT=${1:-local}

if [[ "$ENVIRONMENT" != "local" && "$ENVIRONMENT" != "server" ]]; then
    echo "❌ Invalid environment. Use 'local' or 'server'"
    echo "Usage: $0 [local|server]"
    exit 1
fi

echo "🚀 Deploying NotebookLLM to k3s cluster ($ENVIRONMENT environment)..."

# Check if k3s is running
if ! kubectl cluster-info &> /dev/null; then
    echo "❌ k3s cluster is not accessible. Please ensure k3s is running and kubectl is configured."
    exit 1
fi

# Set namespace based on environment
if [[ "$ENVIRONMENT" == "local" ]]; then
    NAMESPACE="notebookllm-local"
    DOMAIN="localhost"
else
    NAMESPACE="notebookllm-prod"
    DOMAIN="yourdomain.com"
    echo "⚠️  Remember to update server configuration files with your actual domain and secrets!"
fi

# Build and push Docker images (assuming local registry or you have a registry configured)
echo "📦 Building Docker images..."
cd "$(dirname "$0")/.."

# Build backend image
echo "Building backend image..."
docker build -t notebookllm/backend:latest ../backend

# Build frontend image  
echo "Building frontend image..."
docker build -t notebookllm/frontend:latest ../frontend

# If using local registry, uncomment and modify as needed
# docker tag notebookllm/backend:latest localhost:5000/notebookllm/backend:latest
# docker tag notebookllm/frontend:latest localhost:5000/notebookllm/frontend:latest
# docker push localhost:5000/notebookllm/backend:latest
# docker push localhost:5000/notebookllm/frontend:latest

# Apply Kubernetes manifests
echo "🔧 Applying Kubernetes manifests..."
cd k3s/$ENVIRONMENT

# Create namespace
echo "Creating namespace..."
kubectl apply -f namespace.yaml

# Deploy database and cache first
echo "Deploying PostgreSQL..."
kubectl apply -f postgres.yaml

echo "Deploying Redis..."
kubectl apply -f redis.yaml

# Wait for database to be ready
echo "⏳ Waiting for PostgreSQL to be ready..."
kubectl wait --for=condition=ready pod -l app=postgres -n $NAMESPACE --timeout=300s

echo "⏳ Waiting for Redis to be ready..."
kubectl wait --for=condition=ready pod -l app=redis -n $NAMESPACE --timeout=300s

# Deploy backend services
echo "Deploying backend services..."
kubectl apply -f backend.yaml

# Wait for backend to be ready
echo "⏳ Waiting for backend to be ready..."
kubectl wait --for=condition=ready pod -l app=backend -n $NAMESPACE --timeout=300s

# Deploy frontend
echo "Deploying frontend..."
kubectl apply -f frontend.yaml

# Wait for frontend to be ready
echo "⏳ Waiting for frontend to be ready..."
kubectl wait --for=condition=ready pod -l app=frontend -n $NAMESPACE --timeout=300s

# Deploy ingress
echo "Deploying ingress..."
kubectl apply -f ingress.yaml

# Deploy HPA for server environment
if [[ "$ENVIRONMENT" == "server" ]]; then
    echo "Deploying Horizontal Pod Autoscalers..."
    kubectl apply -f hpa.yaml
fi

echo "✅ Deployment completed successfully!"
echo ""
echo "🌐 Application URLs:"
if [[ "$ENVIRONMENT" == "local" ]]; then
    echo "Frontend: http://localhost/"
    echo "Backend API: http://localhost/api"
else
    echo "Frontend: https://$DOMAIN/"
    echo "Backend API: https://$DOMAIN/api"
fi
echo ""
echo "📊 To check deployment status, run: ./verify.sh $ENVIRONMENT"
echo "🗑️  To tear down the deployment, run: ./teardown.sh $ENVIRONMENT"
