terraform {
  required_version = ">= 1.10"

  required_providers {
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 5.0"
    }
  }

  # State lives in an R2 bucket that is created by hand and deliberately kept
  # out of this configuration. Credentials come from AWS_ACCESS_KEY_ID and
  # AWS_SECRET_ACCESS_KEY (see README.md). Backend blocks cannot use variables,
  # so the account ID is written out in the endpoint.
  backend "s3" {
    bucket = "davidhartingdotcom-terraform-state"
    key    = "cloudflare/terraform.tfstate"
    region = "auto"

    endpoints = {
      s3 = "https://REPLACE_WITH_ACCOUNT_ID.r2.cloudflarestorage.com"
    }

    use_path_style              = true
    skip_credentials_validation = true
    skip_metadata_api_check     = true
    skip_region_validation      = true
    skip_requesting_account_id  = true
    skip_s3_checksum            = true
  }
}

# Reads CLOUDFLARE_API_TOKEN from the environment.
provider "cloudflare" {}
