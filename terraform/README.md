# Cloudflare R2 with Terraform

This directory manages the site's R2 buckets: both private buckets, both public buckets, the public buckets' custom domains, the private buckets' CORS and lifecycle rules, and the app's R2 API tokens. It is applied by hand from a laptop. Nothing runs it in CI.

State lives in the `davidhartingdotcom-terraform-state` R2 bucket, which is created by hand and is not managed here. State contains the app's token secrets, so that bucket must stay private.

## Credentials

Running Terraform needs two credentials. Put them in `terraform/secrets.env`, which is gitignored. mise loads it whenever you are inside this directory.

```sh
# Cloudflare API token for Terraform itself (My Profile -> API Tokens -> Create custom token):
#   Account > Workers R2 Storage: Edit
#   Account > Account API Tokens: Edit
#   Zone > DNS: Edit, for davidharting.com
CLOUDFLARE_API_TOKEN=

# R2 key pair for the state bucket only (R2 -> Manage API tokens -> Object Read & Write,
# scoped to davidhartingdotcom-terraform-state).
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
```

## First run

1. Create the `davidhartingdotcom-terraform-state` bucket in the R2 dashboard.
2. Fill in `secrets.env` as above.
3. Replace the placeholders in `terraform.tfvars` and the backend endpoint in `versions.tf` with the account ID and the davidharting.com zone ID. Neither is a secret.
4. Check the private bucket names in `buckets.tf` against the dashboard.
5. `terraform init`, then commit the `.terraform.lock.hcl` it writes.
6. Adopt the existing custom domains. The provider cannot import them, so creating one that already exists fails. For each environment, staging first: remove the domain from the bucket's settings in the dashboard, then `terraform apply -target='cloudflare_r2_custom_domain.public["staging"]'`. The domain serves nothing until Cloudflare reissues its certificate.
7. `terraform plan`. Expect the four buckets to be imported and the rest to be created. The CORS and lifecycle rules overwrite whatever is set on the private buckets today.
8. `terraform apply`.
9. Copy the app credentials into Render (below), then delete the old R2 token in the dashboard.

## App credentials for Render

```sh
terraform output -json app_r2_credentials
```

This prints `R2_ACCESS_KEY_ID` and `R2_SECRET_ACCESS_KEY` for `production` and `staging`. Put them wherever Render holds those values for each environment.

To rotate a token, replace it and copy the new values into Render:

```sh
terraform apply -replace='cloudflare_account_token.app["staging"]'
```
