"""
Custom MySQL engine compatible with MariaDB 10.4 (XAMPP).

Django 5.2 requires MariaDB >= 10.5 and forces INSERT ... RETURNING on any
MariaDB connection (via `can_return_columns_from_insert = self.connection.mysql_is_mariadb`).
MariaDB 10.4 doesn't support RETURNING, which breaks every INSERT (including
recording applied migrations) with:
    MySQLdb.ProgrammingError: near 'RETURNING `django_migrations`.`id`'

This backend:
  * sets minimum_database_version = (10, 3) so the generic check passes;
  * forces can_return_columns_from_insert = False (and bulk variant) so
    Django falls back to INSERT; SELECT LAST_INSERT_ID() which works on 10.4.
"""
from django.db.backends.mysql import base as mysql_base
from django.db.backends.mysql import features as mysql_features


class DatabaseFeatures(mysql_features.DatabaseFeatures):
    # MariaDB 10.5+ only. Force off so Django doesn't emit INSERT ... RETURNING.
    can_return_columns_from_insert = False
    can_return_rows_from_bulk_insert = False

    # Django 5.2 requires MariaDB >= 10.5 ; lower it for XAMPP's 10.4.
    minimum_database_version = (10, 3)


class DatabaseWrapper(mysql_base.DatabaseWrapper):
    features_class = DatabaseFeatures
