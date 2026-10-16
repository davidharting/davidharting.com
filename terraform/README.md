# Cloudflare R2 with Terraform

This directory manages the site's Cloudflare R2 setup: the private and public buckets, the private bucket's CORS and lifecycle rules, the public bucket's custom domain, and the app's R2 API token. Production's buckets are adopted, and the rest of its setup follows in a later PR. It runs in GitHub Actions, and applies wait for David's approval.

State lives in the `davidhartingdotcom-terraform-state` R2 bucket, which is created by hand and is not managed here. State contains the app's token secrets, so that bucket must stay private.

## Running it

Terraform runs in GitHub Actions (`.github/workflows/terraform.yml`), so no laptop needs the Cloudflare tokens:

- **Plan** runs on every PR that touches `terraform/`, and on demand: Actions -> Terraform -> Run workflow, pick the branch, choose `plan`. The plan shows on the run's summary page, and as a `terraform-plan` file to download.
- **Apply** runs on demand only: the same, choosing `apply`. The run waits until David approves it on the `Terraform Apply Environment` environment, then plans again and applies that plan. Run `plan` first and read it.

## Credentials

Repository secrets (Settings -> Secrets and variables -> Actions), used by plan and apply:

- `TF_STATE_ACCESS_KEY_ID`, `TF_STATE_SECRET_ACCESS_KEY`: an R2 key pair with Object Read & Write on `davidhartingdotcom-terraform-state` only (R2 -> Manage API tokens).
- `CLOUDFLARE_API_TOKEN_READ`: a custom API token (My Profile -> API Tokens) with Account > Workers R2 Storage: Read, Account > Account API Tokens: Read, and Zone > DNS: Read on davidharting.com. Plans use it, so a plan cannot change anything.

Environment secret on the `Terraform Apply Environment` environment (Settings -> Environments), used only by apply:

- `CLOUDFLARE_API_TOKEN`: a custom API token with Account > Workers R2 Storage: Edit, Account > Account API Tokens: Edit, and Zone > DNS: Edit on davidharting.com.

Give the `Terraform Apply Environment` environment David as a required reviewer.

To run locally instead, put `CLOUDFLARE_API_TOKEN`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in `terraform/secrets.env` (gitignored). mise loads it whenever you are inside this directory.

## App credentials for Render

Never print these in Actions: the repo is public, so its logs are too. Read them locally, which needs only the state key pair in `secrets.env` (no Cloudflare token):

```sh
terraform init
terraform output -json app_r2_credentials
```

This prints `R2_ACCESS_KEY_ID` and `R2_SECRET_ACCESS_KEY` for each environment. Paste them into Render's `secrets.env` and `staging.secrets.env` secret files.

To rotate a token, run locally with the full credentials, then copy the new values into Render:

```sh
terraform apply -replace='cloudflare_account_token.app["staging"]'
```
