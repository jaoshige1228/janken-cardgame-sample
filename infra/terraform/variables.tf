variable "aws_region" {
  type        = string
  description = "AWS region"
  default     = "ap-northeast-1"
}

variable "project_name" {
  type        = string
  description = "Name prefix for resources"
  default     = "janken-card"
}

variable "lightsail_availability_zone" {
  type        = string
  description = "Lightsail AZ (e.g. ap-northeast-1a)"
  default     = "ap-northeast-1a"
}

variable "lightsail_instance_bundle_id" {
  type        = string
  description = "Lightsail instance bundle (e.g. small_3_0)"
  default     = "small_3_0"
}

variable "lightsail_db_bundle_id" {
  type        = string
  description = "Lightsail database bundle (e.g. micro_2_0)"
  default     = "micro_2_0"
}

variable "db_password" {
  type        = string
  description = "Lightsail MySQL master password"
  sensitive   = true
}

variable "app_key" {
  type        = string
  description = "Laravel APP_KEY (php artisan key:generate --show)"
  sensitive   = true
}

variable "reverb_app_id" {
  type        = string
  description = "Laravel Reverb REVERB_APP_ID"
}

variable "reverb_app_key" {
  type        = string
  description = "Laravel Reverb REVERB_APP_KEY (must match VITE_REVERB_APP_KEY)"
  sensitive   = true
}

variable "reverb_app_secret" {
  type        = string
  description = "Laravel Reverb REVERB_APP_SECRET"
  sensitive   = true
}
