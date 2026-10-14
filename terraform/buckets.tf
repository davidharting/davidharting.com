locals {
  # Each environment has a private bucket (the r2-private disk) and a public
  # bucket (the r2-public disk). Previews use staging. Production joins once
  # staging has applied cleanly.
  environments = {
    staging = {
      private_bucket = "staging-davidhartingdotcom-private"
      public_bucket  = "staging-davidhartingdotcom-public"
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
