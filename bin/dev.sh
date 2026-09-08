#!/bin/bash
echo "Starting PHP backend on http://localhost:8000..."
cd "$(dirname "$0")/../public" && php -S localhost:8000 router.php &
PHP_PID=$!
echo "Starting Vite dev server on http://localhost:5173..."
cd "$(dirname "$0")/../react-app" && npm run dev &
VITE_PID=$!
echo ""
echo "========================================="
echo "Backend:  http://localhost:8000"
echo "Frontend: http://localhost:5173"
echo "========================================="
trap "kill $PHP_PID $VITE_PID 2>/dev/null" EXIT
wait
