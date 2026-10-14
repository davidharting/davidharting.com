# R2's S3 API takes the token ID as the access key and the SHA-256 of the
# token value as the secret. Read them with `terraform output -json app_r2_credentials`.
output "app_r2_credentials" {
  description = "R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY for each environment."
  sensitive   = true
  value = {
    for env, token in cloudflare_account_token.app : env => {
      R2_ACCESS_KEY_ID     = token.id
      R2_SECRET_ACCESS_KEY = sha256(token.value)
    }
  }
}
