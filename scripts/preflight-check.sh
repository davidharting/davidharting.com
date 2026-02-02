#!/bin/bash
#
# Pre-flight check for development environment
# Verifies all required tools are available before starting dev:serve
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

errors=0

check_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

check_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    errors=$((errors + 1))
}

check_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo "Running pre-flight checks..."
echo ""

# Check PHP 8.2+
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
    PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
    PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")

    if [[ "$PHP_MAJOR" -gt 8 ]] || [[ "$PHP_MAJOR" -eq 8 && "$PHP_MINOR" -ge 2 ]]; then
        check_pass "PHP $PHP_VERSION (>= 8.2 required)"
    else
        check_fail "PHP $PHP_VERSION found, but >= 8.2 required"
    fi
else
    check_fail "PHP not found"
fi

# Check Composer
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version 2>/dev/null | head -n1 | awk '{print $3}')
    check_pass "Composer $COMPOSER_VERSION"
else
    check_fail "Composer not found"
fi

# Check Node.js
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    check_pass "Node.js $NODE_VERSION"
else
    check_fail "Node.js not found"
fi

# Check npm
if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm --version)
    check_pass "npm $NPM_VERSION"
else
    check_fail "npm not found"
fi

# Check Docker
if command -v docker &> /dev/null; then
    # Check if Docker daemon is running
    if docker info &> /dev/null; then
        DOCKER_VERSION=$(docker --version | awk '{print $3}' | tr -d ',')
        check_pass "Docker $DOCKER_VERSION (daemon running)"
    else
        check_fail "Docker installed but daemon not running"
    fi
else
    check_fail "Docker not found (needed for Postgres container)"
fi

echo ""

if [[ $errors -gt 0 ]]; then
    echo -e "${RED}Pre-flight check failed with $errors error(s)${NC}"
    echo "Please install missing dependencies before running dev:serve"
    exit 1
else
    echo -e "${GREEN}All pre-flight checks passed!${NC}"
    exit 0
fi
