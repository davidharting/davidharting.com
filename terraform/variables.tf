variable "account_id" {
  description = "Cloudflare account that owns the R2 buckets."
  type        = string
}

variable "zone_id" {
  description = "Cloudflare zone for davidharting.com, where the public buckets' custom domains live."
  type        = string
}
