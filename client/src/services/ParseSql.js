export const ParseSql = {
     async exportToSql(elements) {
        //formatting chaotic elements array to a more civilised data array
        const connections = elements.value.filter(el => el.type === 'chickenFoot');
        const tables = elements.value.filter(elem => elem.type === 'table');
        const data = tables.map(table => {
            const rows = elements.value.filter(row => row.parentNode === table.id);
            return {
                name: table.label,
                position: table.position,
                rows: rows.map(row => {
                    const connectedBy = connections
                        .filter(connection => connection.target === row.id)
                        .map(connection => {
                            const sourceRow = elements.value.find(el => el.id === connection.source);
                            const sourceTable = tables.find(table => table.id === sourceRow.parentNode);
                            return {
                                sourceId: connection.source,
                                sourceRowName: sourceRow.label,
                                sourceTableName: sourceTable.label,
                                relationshipType: connection.data.relationshipType
                            };
                        });
                    return {
                        id: row.id,
                        name: row.label,
                        position: row.position,
                        keyMod: row.data.keyMod,
                        sqlType: row.data.sqlType,
                        nullable: row.data.nullable,
                        unsigned: row.data.unsigned,
                        connectedBy: connectedBy
                    };
                }),
            };
        });
        //this part forms the sql script string from formatted array
        let script = '';
        let primary_key = false;
        let index = false;
        let unique = false;
        const foreignKeys = {
            targetTableName: "",
            targetRowName: "",
            rowName: "",
            table: ""
        };
        const foreignKeysArray = [];

        data.forEach((table) => {
            script += `CREATE TABLE \`${table.name}\`(\n\t`;
            table.rows.forEach((row) => {
                script += `\`${row.name}\` ${row.sqlType}`
                //setting modifies that persist with primary keys
                if (row.keyMod === "Primary") {
                    primary_key = true;
                    script += ' UNSIGNED ';
                }
                if (row.keyMod === "Index") {
                    index = true;
                }
                if (row.keyMod === "Unique") {
                    unique = true;
                }


                if (primary_key) {
                    script += "AUTO_INCREMENT PRIMARY KEY";
                    primary_key = false;
                }
                if (index) {
                    script += `,\n\tINDEX \`index_key_${row.name}\` (\`${row.name}\`)`
                    index = false;
                }
                if (unique) {
                    script += `,\n\tUNIQUE KEY \`unique_key_${row.name}\` (\`${row.name}\`)`
                    unique = false;
                }
                if (row.connectedBy.length > 0) {
                    row.connectedBy.forEach(function (element) {
                        foreignKeys.table = element.sourceTableName;
                        foreignKeys.rowName = element.sourceRowName;
                        foreignKeys.targetTableName = table.name;
                        foreignKeys.targetRowName = row.name;
                        foreignKeysArray.push(foreignKeys);
                        script += ' UNSIGNED';
                    });
                }
                script += `${(row.nullable ? " NULL" : " NOT NULL")}`;

                script += ",\n\t"

            })

            script = script.slice(0, -3); //cutting the last not needed comma
            script += "\n\t"
            script += ");\n"

        })
        foreignKeysArray.forEach(function (element) {

            script += `ALTER TABLE \`${foreignKeys.targetTableName}\``;
            script += ` ADD CONSTRAINT \`${foreignKeys.targetTableName}_${foreignKeys.targetRowName}_foreign\``;
            script += ` FOREIGN KEY (\`${foreignKeys.targetRowName}\`)`;
            script += ` REFERENCES \`${foreignKeys.table}\`(\`${foreignKeys.rowName}\`);\n`
        });

       return script;
    },
}