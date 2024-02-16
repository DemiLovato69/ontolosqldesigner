import {ref} from "vue";
import  { TableActions } from './TableActions.js';
import {Position} from "@vue-flow/core";
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
            script += `CREATE TABLE \`${table.name}\` (\n\t`;
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
        const rowProps = {
            rowName: '',
            keyMod: '',
            sqlType: '',
            nullable: false,
            unsigned: false
        }


        let statements = sqlScript.split(";");
        statements = statements.map(function (statement) {
            return statement.replace(/\n/g, '').replace(/\t/g, '').replace(/`/g,'').toLowerCase();
        });
        statements.forEach(function (statement) {
            if (statement.trim().startsWith("create table")) {
                const createTableMatch = statement.match(/^create table (\w+) \(([^]*)\)$/);
                if (createTableMatch && createTableMatch[1] && createTableMatch[2]) {
                    const tableName = createTableMatch[1];
                    const tableId = TableActions.addTable(elements, TableStyle, tableName);
                     nodeProps = {
                         id: tableId,
                         label: tableName,
                        data: {

                            toolbarPosition: 'top',
                            toolbarVisible: true,
                            // position: {x: 0, y: 0},
                        },

                    }
                }
                const inBrackets = statement.match(/\(([^]*?)\)$/);

                if (inBrackets && inBrackets[1]) {
                    let rows = inBrackets[1].split(',');

                    rows.forEach(function (element){

                        const rowElements = element.split(' ');

                        if (rowElements[0] !== 'index' && rowElements[0] !== 'unique' )
                            rowProps.rowName = rowElements[0];
                            rowProps.sqlType = rowElements[1].toUpperCase();
                            rowElements.shift(); //removing row name from array, to avoid errors
                            rowElements.includes('not') ? rowProps.nullable = false  : true ;
                            rowElements.includes('unsigned') ? rowProps.unsigned = true : false;
                            rowElements.includes('primary') ? rowProps.keyMod = 'Primary' :  rowProps.keyMod = 'None';
                            rowElements.includes('index') ? rowProps.keyMod = 'Index' :  rowProps.keyMod = 'None';
                            rowElements.includes('unique') ? rowProps.keyMod = 'Unique' :  rowProps.keyMod = 'None';
                            console.log(rowProps)
                            console.log(nodeProps)
                            TableActions.addRow(elements, nodeProps, rowProps);


                    });
                }
            }
        });



        return elements.value;
    },

}