# Cloudflare R2 with Terraform

This directory manages the site's R2 buckets: both private buckets, both public buckets, the public buckets' custom domains, the private buckets' CORS and lifecycle rules, and the app's R2 API tokens. It runs in GitHub Actions, and applies wait for David's approval.

State lives in the `davidhartingdotcom-terraform-state` R2 bucket, which is created by hand and is not managed here. State contains the app's token secrets, so that bucket must stay private.

## Running it

Terraform runs in GitHub Actions (`.github/workflows/terraform.yml`), so no laptop needs the Cloudflare tokens:

- **Plan** runs on every PR that touches `terraform/`, and on demand: Actions -> Terraform -> Run workflow, pick the branch, choose `plan`. The output is in the job log.
- **Apply** runs on demand only: the same, choosing `apply`. The run waits until David approves it on the `cloudflare` environment, then plans again and applies that plan. Run `plan` first and read it.

## Credentials

Repository secrets (Settings -> Secrets and variables -> Actions), used by plan and apply:

- `TF_STATE_ACCESS_KEY_ID`, `TF_STATE_SECRET_ACCESS_KEY`: an R2 key pair with Object Read & Write on `davidhartingdotcom-terraform-state` only (R2 -> Manage API tokens).
- `CLOUDFLARE_API_TOKEN_READ`: a custom API token (My Profile -> API Tokens) with Account > Workers R2 Storage: Read, Account > Account API Tokens: Read, and Zone > DNS: Read on davidharting.com. Plans use it, so a plan cannot change anything.

Environment secret on the `cloudflare` environment (Settings -> Environments), used only by apply:

- `CLOUDFLARE_API_TOKEN`: a custom API token with Account > Workers R2 Storage: Edit, Account > Account API Tokens: Edit, and Zone > DNS: Edit on davidharting.com.

Give the `cloudflare` environment David as a required reviewer.

To run locally instead, put `CLOUDFLARE_API_TOKEN`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in `terraform/secrets.env` (gitignored). mise loads it whenever you are inside this directory.

## First run

1. Create the `davidhartingdotcom-terraform-state` bucket in the R2 dashboard.
2. Create the credentials and the `cloudflare` environment above.
3. Replace the placeholders in `terraform.tfvars` and the backend endpoint in `versions.tf` with the account ID and the davidharting.com zone ID. Neither is a secret.
4. Check the private bucket names in `buckets.tf` against the dashboard.
5. Commit a `.terraform.lock.hcl` that covers CI and your laptop: `terraform providers lock -platform=linux_amd64 -platform=darwin_arm64`.
6. Adopt the existing custom domains. The provider cannot import them, so creating one that already exists fails. Staging first: remove `staging.cdn.davidharting.com` from its bucket's settings in the dashboard, apply, and check it serves again. That apply also fails to create `cdn.davidharting.com`, which still exists; that is expected. Then remove `cdn.davidharting.com` and apply again. Each domain serves nothing until Cloudflare reissues its certificate.
7. Plan. Expect the four buckets to be imported and the rest to be created. The CORS and lifecycle rules overwrite whatever is set on the private buckets today.
8. Apply.
9. Copy the app credentials into Render (below), then delete the old R2 token in the dashboard.

## App credentials for Render

Never print these in Actions: the repo is public, so its logs are too. Read them locally, which needs only the state key pair in `secrets.env` (no Cloudflare token):

```sh
terraform init
terraform output -json app_r2_credentials
```

This prints `R2_ACCESS_KEY_ID` and `R2_SECRET_ACCESS_KEY` for `production` and `staging`. Paste them into Render's `secrets.env` and `staging.secrets.env` secret files.

To rotate a token, run locally with the full credentials, then copy the new values into Render:

```sh
terraform apply -replace='cloudflare_account_token.app["staging"]'
```
