locals {
  # Each environment has a private bucket (the r2-private disk) and a public
  # bucket behind a custom domain (the r2-public disk). Previews use staging.
  environments = {
    production = {
      private_bucket = "davidhartingdotcom-private"
      public_bucket  = "davidhartingdotcom-public"
      public_domain  = "cdn.davidharting.com"
      upload_origins = ["https://davidharting.com"]
    }
    staging = {
      private_bucket = "staging-davidhartingdotcom-private"
      public_bucket  = "staging-davidhartingdotcom-public"
      public_domain  = "staging.cdn.davidharting.com"
      upload_origins = ["https://davidhartingdotcom-web-pr-*.onrender.com"]
    }
  }
}

resource "cloudflare_r2_bucket" "private" {
  for_each = local.environments

  account_id = var.account_id
  name       = each.value.private_bucket
}

resource "cloudflare_r2_bucket" "public" {
  for_each = local.environments

  account_id = var.account_id
  name       = each.value.public_bucket
}

# The buckets already exist, so adopt them instead of creating new ones.
import {
  for_each = local.environments
  to       = cloudflare_r2_bucket.private[each.key]
  id       = "${var.account_id}/${each.value.private_bucket}/default"
}

import {
  for_each = local.environments
  to       = cloudflare_r2_bucket.public[each.key]
  id       = "${var.account_id}/${each.value.public_bucket}/default"
}

# Livewire uploads memory photos straight from the browser to the private
# bucket with a presigned PUT, and signs x-amz-acl into that URL.
resource "cloudflare_r2_bucket_cors" "private" {
  for_each = local.environments

  account_id  = var.account_id
  bucket_name = cloudflare_r2_bucket.private[each.key].name

  rules = [{
    id = "Livewire browser uploads"
    allowed = {
      methods = ["PUT"]
      origins = each.value.upload_origins
      headers = ["Content-Type", "x-amz-acl"]
    }
    max_age_seconds = 3600
  }]
}

# Setting a lifecycle replaces the bucket's whole lifecycle configuration, so
# R2's default seven-day multipart abort rule is restated here.
resource "cloudflare_r2_bucket_lifecycle" "private" {
  for_each = local.environments

  account_id  = var.account_id
  bucket_name = cloudflare_r2_bucket.private[each.key].name

  rules = [
    {
      id      = "Expire Livewire temporary uploads"
      enabled = true
      conditions = {
        prefix = "livewire-tmp/"
      }
      delete_objects_transition = {
        condition = {
          type    = "Age"
          max_age = 86400
        }
      }
    },
    {
      id      = "Default Multipart Abort Rule"
      enabled = true
      conditions = {
        prefix = ""
      }
      abort_multipart_uploads_transition = {
        condition = {
          type    = "Age"
          max_age = 604800
        }
      }
    },
  ]
}

# The provider cannot import custom domains. See README.md for adopting the
# ones that already exist.
resource "cloudflare_r2_custom_domain" "public" {
  for_each = local.environments

  account_id  = var.account_id
  zone_id     = var.zone_id
  bucket_name = cloudflare_r2_bucket.public[each.key].name
  domain      = each.value.public_domain
  enabled     = true
}
