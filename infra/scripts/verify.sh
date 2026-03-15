#!/bin/bash

set -e

# Parse command line arguments
ENVIRONMENT=${1:-local}

if [[ "$ENVIRONMENT" != "local" && "$ENVIRONMENT" != "server" ]]; then
    echo "❌ Invalid environment. Use 'local' or 'server'"
    echo "Usage: $0 [local|server]"
    exit 1
fi

echo "🔍 Verifying NotebookLLM deployment in k3s cluster ($ENVIRONMENT environment)..."

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
fi

echo ""
echo "📊 Namespace Status:"
kubectl get namespace $NAMESPACE || echo "❌ Namespace $NAMESPACE not found"

echo ""
echo "🏗️  Pods Status:"
kubectl get pods -n $NAMESPACE || echo "❌ No pods found in namespace $NAMESPACE"

echo ""
echo "🔌 Services Status:"
kubectl get services -n $NAMESPACE || echo "❌ No services found in namespace $NAMESPACE"

echo ""
echo "🌐 Ingress Status:"
kubectl get ingress -n $NAMESPACE || echo "❌ No ingress found in namespace $NAMESPACE"

echo ""
echo "💾 Persistent Volumes Status:"
kubectl get pvc -n $NAMESPACE || echo "❌ No PVCs found in namespace $NAMESPACE"

echo ""
echo "📝 Detailed Pod Status:"
for pod in $(kubectl get pods -n $NAMESPACE -o jsonpath='{.items[*].metadata.name}' 2>/dev/null || true); do
    echo "---"
    echo "Pod: $pod"
    kubectl get pod $pod -n $NAMESPACE -o wide
    echo "Events:"
    kubectl describe pod $pod -n $NAMESPACE | grep -A 10 "Events:" || true
    echo ""
done

echo ""
echo "🔍 Service Endpoints:"
kubectl get endpoints -n $NAMESPACE || echo "❌ No endpoints found"

echo ""
echo "📋 Recent Logs (last 20 lines):"
for pod in $(kubectl get pods -n $NAMESPACE -o jsonpath='{.items[*].metadata.name}' 2>/dev/null || true); do
    echo "--- $pod logs ---"
    kubectl logs $pod -n $NAMESPACE --tail=20 || echo "❌ Could not get logs for $pod"
    echo ""
done

echo ""
echo "🌐 Access URLs:"
if [[ "$ENVIRONMENT" == "local" ]]; then
    echo "Frontend: http://localhost/"
    echo "Backend API: http://localhost/api"
else
    echo "Frontend: https://$DOMAIN/"
    echo "Backend API: https://$DOMAIN/api"
fi

echo ""
echo "📈 HPA Status (server only):"
if [[ "$ENVIRONMENT" == "server" ]]; then
    kubectl get hpa -n $NAMESPACE || echo "❌ No HPA found"
fi

echo ""
echo "✅ Verification completed!"
echo ""
echo "🔧 Troubleshooting Commands:"
echo "- View pod logs: kubectl logs <pod-name> -n $NAMESPACE"
echo "- Describe pod: kubectl describe pod <pod-name> -n $NAMESPACE"
echo "- Get events: kubectl get events -n $NAMESPACE --sort-by=.metadata.creationTimestamp"
echo "- Exec into pod: kubectl exec -it <pod-name> -n $NAMESPACE -- bash"
