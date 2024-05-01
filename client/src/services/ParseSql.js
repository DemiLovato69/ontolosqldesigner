import {ref} from "vue";
import  { TableActions } from './TableActions.js';
import {Position} from "@vue-flow/core";
export const ParseSql = {

     async exportToSql(elements) {
         console.log(elements.value)
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
                                sourceId: connection.source,
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
        let primary_key_field = '';
        let index = false;
        let unique = false;
        const foreignKeys = {
            targetTableName: "",
            targetRowName: "",
            rowName: "",
            table: ""
        };
        let foreignKeysArray = [];

        data.forEach((table) => {
            script += `CREATE TABLE \`${table.name}\` (\n\t`;
            table.rows.forEach((row) => {
                script += `\`${row.name}\` ${row.sqlType}`

                if (row.keyMod === "Primary") {
                    primary_key_field = row.name;
                    primary_key = true;
                }
                if (row.keyMod === "Index") {
                    index = true;
                }
                if (row.keyMod === "Unique") {
                    unique = true;
                }

                if (row.connectedTo.length > 0) {
                    row.connectedTo.forEach(function (element) {
                        foreignKeys.table = table.name;
                        foreignKeys.rowName = row.name;
                        foreignKeys.targetTableName = element.targetTableName;
                        foreignKeys.targetRowName = element.targetRowName;
                        foreignKeysArray.push(foreignKeys);
                    });
                }

                if (index) {
                    script += `,\n\tINDEX \`index_key_${row.name}\` (\`${row.name}\`)`
                }
                if (unique) {
                    script += `,\n\tUNIQUE KEY \`unique_key_${row.name}\` (\`${row.name}\`)`
                }

                if(!index && !unique) {
                    script += `${(row.nullable ? " NULL" : " NOT NULL")}`;
                    script += `${(row.unsigned ? " UNSIGNED" : "")}`;
                }
                index = false;
                unique = false;

                script += ",\n\t";

            })
            if (primary_key) {
                script += `CONSTRAINT PK_${primary_key_field} PRIMARY KEY (${primary_key_field}),`
                primary_key = false;
                script += "\n\t"
            }

            //cutting the last not needed comma
            script = script.slice(0, -3);
            script += "\n"




            script += ");\n"

        })

         foreignKeysArray.forEach(function (element) {
             script += `ALTER TABLE \`${foreignKeys.table}\``;
             script += `ADD CONSTRAINT \`FK_${foreignKeys.targetTableName}_${foreignKeys.targetRowName}\``;
             script += ` FOREIGN KEY (\`${foreignKeys.rowName}\`)`;
             script += ` REFERENCES \`${foreignKeys.targetTableName}\`(\`${foreignKeys.targetRowName}\`)\n`
             script += "\n\t"
         });

       return script;
    },
    async importSql(sqlScript) {
        const TableStyle = {
            display: 'flex',
            border: '1px solid #10b981',
            background: '#007BFF',
            borderColor: '#007BFF',
            color: 'white',
            borderRadius: '5px',
            width: '350px',
            height: '40px',
            alignItems: 'center',
            justifyContent: 'space-between',
        }

        const elements = ref([
            {
            },
        ])
        let nodeProps = {};
        let rowProps = {
            rowName: '',
            keyMod: '',
            sqlType: '',
            nullable: false,
            unsigned: false
        }


        let statements = sqlScript.split("\n");
        statements = statements.map(function (statement) {
            statement = statement.toLowerCase()
            return statement.replace(/\n/g, '').replace(/\t/g, '').replace(/`/g,'') //cutting spaces and line ends
                .replace(/--.+/, '').replace(/\/\*.+/, '').replace('--', '') //cutting comments
                .replace(/^set.+/, '').replace('commit;', '').replace('start transaction;', '');
        });
        statements = statements.join("");
        statements = statements.split(";");

        const alterTableElements = [];
        const createTableElements = [];
        statements.forEach(statement => {
            statement.trim().toLowerCase().startsWith("alter table")
                ? alterTableElements.push(statement)
                : createTableElements.push(statement);
            });
        statements = alterTableElements.concat(createTableElements)


        let primaryKeysArray = []
        let uniqueKeysArray = []
        let indexesArray = []
        let foreignKeysArray = []
        let primaryKeys = {
            table: '',
            rowName: ''
        }
        let uniqueKeys = {
            table: '',
            rowName: ''
        }
        let indexes = [{
            table: '',
            rowName: ''
        }]
        let foreignKeys = {
            table: '',
            rowName: '',
            targetRowName: '',
            targetTableName: '',
            foreignKeyName: '',
            sourceId: '',
            targetId: ''
        }
        let currentTable = '';
        statements.forEach(function (statement) {

            if (statement.trim().startsWith("alter table")) {

                let alterTableStatements = statement.split(',');
                alterTableStatements.forEach(function (alterTableStatement) {

                    let alterTableMatch = alterTableStatement.match(/alter\s+table\s+(.+\s+)+add/);
                    if (alterTableMatch){
                        currentTable = alterTableMatch[1].trim();
                    }

                    let primaryKeyMatch = alterTableStatement.match(/add\s+primary\s+key\s+\((.+)\)/);
                    if (primaryKeyMatch){
                        primaryKeys.table = currentTable.trim();
                        primaryKeys.rowName = primaryKeyMatch[1].trim();
                        primaryKeysArray.push(primaryKeys)
                        primaryKeys = {};
                    }

                    let uniqueMatch = alterTableStatement.match(/add\s+unique\s+key\s+.+\((.+)\)/);
                    if (uniqueMatch) {
                        uniqueKeys.table = currentTable.trim();
                        uniqueKeys.rowName = uniqueMatch[1].trim();
                        uniqueKeysArray.push(uniqueKeys)
                        uniqueKeys = {};
                    }

                    let foreignKeyMatch = alterTableStatement.match(/alter\s+table\s+(.+\s+)+add\s+constraint\s+(.+)foreign\s+key\s+\((.+)\)\s+references\s+(.+)\s+\((.+)\)/);
                    if (foreignKeyMatch) {
                        foreignKeys.table = currentTable.trim();
                        foreignKeys.foreignKeyName = foreignKeyMatch[2].trim();
                        foreignKeys.rowName = foreignKeyMatch[3].trim();
                        foreignKeys.targetTableName = foreignKeyMatch[4].trim();
                        foreignKeys.targetRowName = foreignKeyMatch[5].trim();
                        foreignKeysArray.push(foreignKeys)
                        foreignKeys = {};
                    }

                    let indexMatch = alterTableStatement.match(/add\s+key\s+.+\((.+)\)/);
                    if (indexMatch) {
                        indexes.table = currentTable.trim();
                        indexes.rowName = indexMatch[1];
                        indexesArray.push(indexes)
                        indexes = {};
                    }

                });
            }


            if (statement.trim().startsWith("create table")) {


                const tableName = statement.match(/create\s+table\s+(.+\s+)+\(/)[1].trim();
                const tableId = TableActions.addTable(elements, TableStyle, tableName);
                nodeProps = {
                    id: tableId,
                    label: tableName,
                    data: {

                        toolbarPosition: 'top',
                        toolbarVisible: true,
                        position: {x: 0, y: 0},
                    },

                }


                const inBrackets = statement.match(/\((.+)\)/);

                if (inBrackets && inBrackets[1]) {
                    let rows = inBrackets[1].split(',');

                     rows.forEach(function (element) {

                         let rowElements = element.split(' ');
                         rowElements = rowElements.filter(item => item !== ""); //removing empty elements
                         rowProps.rowName = rowElements[0];
                         rowProps.sqlType = rowElements[1].toUpperCase();
                         rowProps.nullable = !rowElements.includes('not');
                         rowProps.unsigned = rowElements.includes('unsigned');

                         if (rowElements.includes('primary')) {
                             rowProps.keyMod = 'Primary';
                             rowProps.unsigned = true;
                         }
                         else {
                             rowProps.keyMod = 'None';
                         }

                         primaryKeysArray.forEach(function (primaryKey) {
                             if (primaryKey.table === tableName && primaryKey.rowName === rowProps.rowName) {
                                 rowProps.keyMod = 'Primary';
                                 rowProps.unsigned = true;
                             }
                         })
                         indexesArray.forEach(function (index) {
                             if (index.table === tableName && index.rowName === rowProps.rowName) {
                                 rowProps.keyMod = 'Index';
                             }
                         })
                         uniqueKeysArray.forEach(function (unique) {
                             if (unique.table === tableName && unique.rowName === rowProps.rowName) {
                                 rowProps.keyMod = 'Unique';
                             }
                         })

                         let rowId = TableActions.addRow(elements, nodeProps, rowProps);
                         foreignKeysArray.forEach(function (foreignKey){

                             if (foreignKey.rowName === rowProps.rowName) {

                                 foreignKey.sourceId = rowId;

                             }
                             if (foreignKey.targetRowName === rowProps.rowName) {
                                 foreignKey.targetId = rowId;
                             }
                         })
                         rowProps = {
                             rowName: '',
                             keyMod: '',
                             sqlType: '',
                             nullable: false,
                             unsigned: false
                         }
                     });
                }
            }
        });

        foreignKeysArray.forEach(function (foreignKey) {
            TableActions.addEdge(elements, foreignKey.sourceId, foreignKey.targetId)
        })
        return elements.value;
    },
}