export const MYSQL_TYPES = {
    'Numeric': [
        { value: 'TINYINT', label: 'TINYINT' },
        { value: 'SMALLINT', label: 'SMALLINT' },
        { value: 'MEDIUMINT', label: 'MEDIUMINT' },
        { value: 'INT', label: 'INT' },
        { value: 'BIGINT', label: 'BIGINT' },
        { value: 'DECIMAL(10,2)', label: 'DECIMAL' },
        { value: 'FLOAT', label: 'FLOAT' },
        { value: 'DOUBLE', label: 'DOUBLE' },
        { value: 'BIT', label: 'BIT' },
    ],
    'String': [
        { value: 'CHAR(255)', label: 'CHAR' },
        { value: 'VARCHAR(255)', label: 'VARCHAR' },
        { value: 'TINYTEXT', label: 'TINYTEXT' },
        { value: 'TEXT', label: 'TEXT' },
        { value: 'MEDIUMTEXT', label: 'MEDIUMTEXT' },
        { value: 'LONGTEXT', label: 'LONGTEXT' },
        { value: 'BINARY(255)', label: 'BINARY' },
        { value: 'VARBINARY(255)', label: 'VARBINARY' },
        { value: 'TINYBLOB', label: 'TINYBLOB' },
        { value: 'BLOB', label: 'BLOB' },
        { value: 'MEDIUMBLOB', label: 'MEDIUMBLOB' },
        { value: 'LONGBLOB', label: 'LONGBLOB' },
        { value: "ENUM('')", label: 'ENUM' },
        { value: "SET('')", label: 'SET' },
    ],
    'Date & Time': [
        { value: 'DATE', label: 'DATE' },
        { value: 'TIME', label: 'TIME' },
        { value: 'DATETIME', label: 'DATETIME' },
        { value: 'TIMESTAMP', label: 'TIMESTAMP' },
        { value: 'YEAR', label: 'YEAR' },
    ],
    'Other': [
        { value: 'JSON', label: 'JSON' },
        { value: 'UUID', label: 'UUID' },
        { value: 'BOOLEAN', label: 'BOOLEAN' },
    ],
}

export const POSTGRESQL_TYPES = {
    'Numeric': [
        { value: 'SMALLINT', label: 'SMALLINT' },
        { value: 'INTEGER', label: 'INTEGER' },
        { value: 'BIGINT', label: 'BIGINT' },
        { value: 'DECIMAL(10,2)', label: 'DECIMAL' },
        { value: 'NUMERIC(10,2)', label: 'NUMERIC' },
        { value: 'REAL', label: 'REAL' },
        { value: 'DOUBLE PRECISION', label: 'DOUBLE PRECISION' },
        { value: 'SMALLSERIAL', label: 'SMALLSERIAL' },
        { value: 'SERIAL', label: 'SERIAL' },
        { value: 'BIGSERIAL', label: 'BIGSERIAL' },
    ],
    'String': [
        { value: 'CHAR(255)', label: 'CHAR' },
        { value: 'VARCHAR(255)', label: 'VARCHAR' },
        { value: 'TEXT', label: 'TEXT' },
        { value: 'BYTEA', label: 'BYTEA' },
        { value: "ENUM('')", label: 'ENUM' },
    ],
    'Date & Time': [
        { value: 'DATE', label: 'DATE' },
        { value: 'TIME', label: 'TIME' },
        { value: 'TIMESTAMP', label: 'TIMESTAMP' },
        { value: 'TIMESTAMPTZ', label: 'TIMESTAMPTZ' },
        { value: 'INTERVAL', label: 'INTERVAL' },
    ],
    'Other': [
        { value: 'BOOLEAN', label: 'BOOLEAN' },
        { value: 'JSON', label: 'JSON' },
        { value: 'JSONB', label: 'JSONB' },
        { value: 'UUID', label: 'UUID' },
    ],
}

export const MSACCESS_TYPES = {
    'Numeric': [
        { value: 'BYTE', label: 'BYTE' },
        { value: 'SHORT', label: 'SHORT' },
        { value: 'LONG', label: 'LONG' },
        { value: 'SINGLE', label: 'SINGLE' },
        { value: 'DOUBLE', label: 'DOUBLE' },
        { value: 'CURRENCY', label: 'CURRENCY' },
        { value: 'DECIMAL(10,2)', label: 'DECIMAL' },
        { value: 'AUTOINCREMENT', label: 'AUTOINCREMENT' },
    ],
    'String': [
        { value: 'TEXT(255)', label: 'TEXT' },
        { value: 'CHAR(255)', label: 'CHAR' },
        { value: 'VARCHAR(255)', label: 'VARCHAR' },
        { value: 'MEMO', label: 'MEMO' },
    ],
    'Date & Time': [
        { value: 'DATETIME', label: 'DATETIME' },
        { value: 'DATE', label: 'DATE' },
    ],
    'Other': [
        { value: 'YESNO', label: 'YESNO' },
        { value: 'OLEOBJECT', label: 'OLEOBJECT' },
        { value: 'GUID', label: 'GUID' },
    ],
}

export const SQLSERVER_TYPES = {
    'Numeric': [
        { value: 'TINYINT', label: 'TINYINT' },
        { value: 'SMALLINT', label: 'SMALLINT' },
        { value: 'INT', label: 'INT' },
        { value: 'BIGINT', label: 'BIGINT' },
        { value: 'DECIMAL(10,2)', label: 'DECIMAL' },
        { value: 'FLOAT', label: 'FLOAT' },
        { value: 'REAL', label: 'REAL' },
        { value: 'MONEY', label: 'MONEY' },
        { value: 'SMALLMONEY', label: 'SMALLMONEY' },
    ],
    'String': [
        { value: 'NVARCHAR(255)', label: 'NVARCHAR' },
        { value: 'VARCHAR(255)', label: 'VARCHAR' },
        { value: 'NCHAR(255)', label: 'NCHAR' },
        { value: 'CHAR(255)', label: 'CHAR' },
        { value: 'NVARCHAR(MAX)', label: 'NVARCHAR(MAX)' },
        { value: 'VARCHAR(MAX)', label: 'VARCHAR(MAX)' },
    ],
    'Date & Time': [
        { value: 'DATE', label: 'DATE' },
        { value: 'TIME', label: 'TIME' },
        { value: 'DATETIME2', label: 'DATETIME2' },
        { value: 'DATETIME', label: 'DATETIME' },
        { value: 'SMALLDATETIME', label: 'SMALLDATETIME' },
        { value: 'DATETIMEOFFSET', label: 'DATETIMEOFFSET' },
    ],
    'Other': [
        { value: 'BIT', label: 'BIT' },
        { value: 'UNIQUEIDENTIFIER', label: 'UNIQUEIDENTIFIER' },
        { value: 'VARBINARY(MAX)', label: 'VARBINARY(MAX)' },
        { value: 'XML', label: 'XML' },
    ],
}

export const ORACLE_TYPES = {
    'Numeric': [
        { value: 'NUMBER', label: 'NUMBER' },
        { value: 'NUMBER(10,2)', label: 'NUMBER(p,s)' },
        { value: 'FLOAT', label: 'FLOAT' },
        { value: 'BINARY_FLOAT', label: 'BINARY_FLOAT' },
        { value: 'BINARY_DOUBLE', label: 'BINARY_DOUBLE' },
    ],
    'String': [
        { value: 'VARCHAR2(255)', label: 'VARCHAR2' },
        { value: 'CHAR(255)', label: 'CHAR' },
        { value: 'NVARCHAR2(255)', label: 'NVARCHAR2' },
        { value: 'NCHAR(255)', label: 'NCHAR' },
        { value: 'CLOB', label: 'CLOB' },
        { value: 'NCLOB', label: 'NCLOB' },
    ],
    'Date & Time': [
        { value: 'DATE', label: 'DATE' },
        { value: 'TIMESTAMP', label: 'TIMESTAMP' },
        { value: 'TIMESTAMP WITH TIME ZONE', label: 'TIMESTAMP WITH TZ' },
        { value: 'INTERVAL YEAR TO MONTH', label: 'INTERVAL YEAR TO MONTH' },
        { value: 'INTERVAL DAY TO SECOND', label: 'INTERVAL DAY TO SECOND' },
    ],
    'Other': [
        { value: 'BLOB', label: 'BLOB' },
        { value: 'RAW(255)', label: 'RAW' },
        { value: 'XMLTYPE', label: 'XMLTYPE' },
    ],
}

export const SQLITE_TYPES = {
    'Numeric': [
        { value: 'INTEGER', label: 'INTEGER' },
        { value: 'REAL', label: 'REAL' },
        { value: 'NUMERIC(10,2)', label: 'NUMERIC' },
    ],
    'String': [
        { value: 'TEXT', label: 'TEXT' },
        { value: 'BLOB', label: 'BLOB' },
    ],
    'Date & Time': [
        { value: 'TEXT', label: 'TEXT (ISO 8601)' },
        { value: 'NUMERIC', label: 'NUMERIC (epoch)' },
    ],
    'Other': [
        { value: 'INTEGER', label: 'BOOLEAN' },
    ],
}

export const ONTOLOGY_TYPES = {
    'Primitive': [
        { value: 'BOOLEAN', label: 'BOOLEAN' },
        { value: 'BYTE', label: 'BYTE' },
        { value: 'SHORT', label: 'SHORT' },
        { value: 'INTEGER', label: 'INTEGER' },
        { value: 'LONG', label: 'LONG' },
        { value: 'FLOAT', label: 'FLOAT' },
        { value: 'DOUBLE', label: 'DOUBLE' },
        { value: 'DECIMAL(10,2)', label: 'DECIMAL' },
        { value: 'STRING', label: 'STRING' },
        { value: "ENUM('')", label: 'ENUM / VALUE TYPE' },
        { value: 'DATE', label: 'DATE' },
        { value: 'TIMESTAMP', label: 'TIMESTAMP' },
        { value: 'ATTACHMENT', label: 'ATTACHMENT' },
        { value: 'ARRAY<STRING>', label: 'ARRAY' },
        { value: 'VECTOR', label: 'VECTOR' },
        { value: 'STRUCT', label: 'STRUCT' },
    ],
    'Geospatial & Media': [
        { value: 'GEOPOINT', label: 'GEOPOINT' },
        { value: 'GEOHASH', label: 'GEOHASH' },
        { value: 'GEOSHAPE', label: 'GEOSHAPE' },
        { value: 'MEDIAREFERENCE', label: 'MEDIA REFERENCE' },
        { value: 'GEOTIMESERIES', label: 'GEOTIME SERIES' },
    ],
}

export function typeGroupsFor(dbType) {
    if (dbType === 'ontology') return ONTOLOGY_TYPES
    if (dbType === 'postgresql') return POSTGRESQL_TYPES
    if (dbType === 'sqlite') return SQLITE_TYPES
    if (dbType === 'oracle') return ORACLE_TYPES
    if (dbType === 'sqlserver') return SQLSERVER_TYPES
    if (dbType === 'msaccess') return MSACCESS_TYPES
    return MYSQL_TYPES
}
