resource "aws_lightsail_database" "mysql" {
  relational_database_name = "${var.project_name}-db"
  availability_zone        = var.lightsail_availability_zone
  master_database_name     = "janken"
  master_username          = "janken"
  master_password          = var.db_password
  blueprint_id             = "mysql_8_0"
  bundle_id                = var.lightsail_db_bundle_id
  skip_final_snapshot      = true
}
