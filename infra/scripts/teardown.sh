#!/bin/bash

set -e

# Parse command line arguments
ENVIRONMENT=${1:-local}

if [[ "$ENVIRONMENT" != "local" && "$ENVIRONMENT" != "server" ]]; then
    echo "❌ Invalid environment. Use 'local' or 'server'"
    echo "Usage: $0 [local|server]"
    exit 1
fi

echo "🗑️  Tearing down NotebookLLM deployment from k3s cluster ($ENVIRONMENT environment)..."

# Check if k3s is running
if ! kubectl cluster-info &> /dev/null; then
    echo "❌ k3s cluster is not accessible. Please ensure k3s is running and kubectl is configured."
    exit 1
fi

# Set namespace based on environment
if [[ "$ENVIRONMENT" == "local" ]]; then
    NAMESPACE="notebookllm-local"
else
    NAMESPACE="notebookllm-prod"
fi

# Ask for confirmation
echo "⚠️  This will remove all NotebookLLM resources from the k3s cluster."
echo "📋 Resources to be deleted:"
echo "   - All deployments, pods, and services in namespace '$NAMESPACE'"
echo "   - All configmaps and secrets in namespace '$NAMESPACE'"
echo "   - All persistent volumes and claims in namespace '$NAMESPACE'"
echo "   - The namespace '$NAMESPACE' itself"
if [[ "$ENVIRONMENT" == "server" ]]; then
    echo "   - Horizontal Pod Autoscalers"
fi
echo ""
read -p "Are you sure you want to continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Teardown cancelled."
    exit 0
fi

echo ""
echo "🔧 Removing Kubernetes resources..."

# Change to environment-specific directory
cd "$(dirname "$0")/../k3s/$ENVIRONMENT"

# Delete HPA first (if server environment)
if [[ "$ENVIRONMENT" == "server" ]]; then
    echo "Removing Horizontal Pod Autoscalers..."
    kubectl delete -f hpa.yaml --ignore-not-found=true
fi

# Delete ingress first (to avoid dependency issues)
echo "Removing ingress..."
kubectl delete -f ingress.yaml --ignore-not-found=true

# Delete backend services
echo "Removing backend services..."
kubectl delete -f backend.yaml --ignore-not-found=true

# Delete frontend
echo "Removing frontend..."
kubectl delete -f frontend.yaml --ignore-not-found=true

# Delete database and cache
echo "Removing Redis..."
kubectl delete -f redis.yaml --ignore-not-found=true

echo "Removing PostgreSQL..."
kubectl delete -f postgres.yaml --ignore-not-found=true

# Delete namespace (this will remove all remaining resources)
echo "Removing namespace..."
kubectl delete -f namespace.yaml --ignore-not-found=true

# Wait for namespace to be fully deleted
echo "⏳ Waiting for namespace to be fully deleted..."
kubectl wait --for=delete namespace/$NAMESPACE --timeout=300s || true

# Clean up any remaining PVCs that might be stuck
echo "🧹 Cleaning up any remaining PVCs..."
kubectl get pvc -n $NAMESPACE 2>/dev/null | awk 'NR>1 {print $1}' | xargs -r kubectl delete pvc -n $NAMESPACE || true

# Clean up any remaining PVs
echo "🧹 Cleaning up any remaining PVs..."
kubectl get pv | grep $NAMESPACE | awk '{print $1}' | xargs -r kubectl delete pv || true

echo ""
echo "✅ Teardown completed successfully!"
echo ""
echo "📊 Verification:"
echo "Namespace status:"
kubectl get namespace $NAMESPACE 2>/dev/null || echo "✅ Namespace '$NAMESPACE' successfully deleted"

echo ""
echo "Remaining resources in cluster (if any):"
kubectl get all -n $NAMESPACE 2>/dev/null || echo "✅ No resources remaining in namespace '$NAMESPACE'"

echo ""
echo "🔄 To redeploy the application, run: ./deploy.sh $ENVIRONMENT"
