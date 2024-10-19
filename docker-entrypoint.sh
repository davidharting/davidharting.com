for secret in /run/secrets/*; do
    if [ -f "$secret" ]; then
        export $(basename "$secret")=$(cat "$secret" | tr -d "\n")
    fi
done
