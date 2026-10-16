data "cloudflare_account_api_token_permission_groups_list" "r2_bucket_item_write" {
  account_id = var.account_id
  name       = "Workers%20R2%20Storage%20Bucket%20Item%20Write" # URL-encoded, as the API filter expects
}

# One token per environment for the app's r2-private and r2-public disks:
# object read, write and list on that environment's two buckets, nothing else.
resource "cloudflare_account_token" "app" {
  for_each = local.environments

  account_id = var.account_id
  name       = "davidhartingdotcom ${each.key} app (Terraform)"

  policies = [{
    effect = "allow"
    permission_groups = [{
      id = data.cloudflare_account_api_token_permission_groups_list.r2_bucket_item_write.result[0].id
    }]
    resources = jsonencode({
      "com.cloudflare.edge.r2.bucket.${var.account_id}_default_${each.value.private_bucket}" = "*"
      "com.cloudflare.edge.r2.bucket.${var.account_id}_default_${each.value.public_bucket}"  = "*"
    })
  }]
}
