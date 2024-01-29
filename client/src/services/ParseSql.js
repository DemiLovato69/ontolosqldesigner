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
                    const connectedTo = connections
                        .filter(connection => connection.source === row.id)
                        .map(connection => {
                            const targetRow = elements.value.find(el => el.id === connection.target);
                            const targetTable = tables.find(table => table.id === targetRow.parentNode);
                            return {
                                targetId: connection.target,
                                targetRowName: targetRow.label,
                                targetTableName: targetTable.label,
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
                        connectedTo: connectedTo
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
            script += `CREATE TABLE \`${table.name}\`
            (     `;
            table.rows.forEach((row) => {
                script += `\`${row.name}\` ${row.sqlType} `
                //setting modifies that persist with primary keys
                if (row.keyMod === "Primary") {
                    primary_key = true;
                    script += "UNSIGNED ";
                }
                if (row.keyMod === "Index") {
                    index = true;
                }
                if (row.keyMod === "Unique") {
                    unique = true;
                }

                script += `${(row.nullable ? "NULL" : "NOT NULL")}`;

                if (primary_key) {
                    script += " AUTO_INCREMENT PRIMARY KEY";
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

                script += ",\n\t"
                if (row.connectedTo.length > 0) {
                    row.connectedTo.forEach(function (element) {
                        foreignKeys.table = table.name;
                        foreignKeys.rowName = row.name;
                        foreignKeys.targetTableName = element.targetTableName;
                        foreignKeys.targetRowName = element.targetRowName;
                        foreignKeysArray.push(foreignKeys);
                    });
                }
            })
            script = script.slice(0, -3); //cutting the last not needed comma
            script += "\n\t"
            script += ");\n"

        })
        foreignKeysArray.forEach(function (element) {
            script += `ALTER TABLE \`${foreignKeys.table}\``;
            script += ` ADD CONSTRAINT \`${foreignKeys.table}_${foreignKeys.rowName}_foreign\``;
            script += ` FOREIGN KEY (\`${foreignKeys.rowName}\`)`;
            script += ` REFERENCES \`${foreignKeys.targetTableName}\`(\`${foreignKeys.targetRowName}\`);\n`
        });

       return script;
    },
}