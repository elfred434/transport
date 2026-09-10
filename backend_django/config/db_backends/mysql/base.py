"""
Custom MySQL engine that supports MariaDB 10.4 (XAMPP).

Django 5.2 requires MariaDB >= 10.5 and enables INSERT ... RETURNING for
MariaDB backends by hardcoding `can_return_columns_from_insert = mysql_is_mariadb`
in DatabaseFeatures. MariaDB 10.4 doesn't support RETURNING, which causes
migrate/insert to fail with::

    MySQLdb.ProgrammingError: (1064, "You have an error in your SQL syntax; ...
    near 'RETURNING `django_migrations`.`id`' at line 1")

This backend forces those flags off so Django falls back to the classic
`INSERT; SELECT LAST_INSERT_ID();` flow that works on 10.4.
"""
from django.db.backends.mysql import base as mysql_base
from django.db.backends.mysql import features as mysql_features


class DatabaseFeatures(mysql_features.DatabaseFeatures):
    # MariaDB 10.5+ only. Force off for 10.4 compatibility.
    can_return_columns_from_insert = False
    can_return_rows_from_bulk_insert = False

    # Bypass the NotSupportedError raised by base.py::check_database_version_supported
    # (minimum_database_version was set to (10,5) for MariaDB in Django 5.2).
    minimum_database_version = (10, 3)


class DatabaseWrapper(mysql_base.DatabaseWrapper):
    features_class = DatabaseFeatures

    def check_database_version_supported(self):
        # Accept any MariaDB >= 10.3 to stay XAMPP-10.4 friendly.
        # Still enforce MySQL 8 if connected to MySQL (not MariaDB).
        from django.core.exceptions import NotSupportedError
        if self.mysql_is_mariadb:
            if self.mysql_version < (10, 3):
                raise NotSupportedError(
                    "MariaDB 10.3 or later is required (found %s)."
                    % ".".join(map(str, self.mysql_version))
                )
            return
        # MySQL : keep Django's default check (>=8.0.11)
        super().check_database_version_supported()
