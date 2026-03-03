for secret in /run/secrets/*; do
    if [ -f "$secret" ]; then
        echo "Exporting secret: $(basename "$secret")"
        export $(basename "$secret")=$(cat "$secret" | tr -d "\n")
    fi
done

exec "$@"

