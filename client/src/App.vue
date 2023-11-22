<script setup>
import { Handle, Position, VueFlow, useVueFlow } from '@vue-flow/core'
import { Background, BackgroundVariant } from '@vue-flow/background'
import { ref, provide, onMounted, onBeforeMount} from 'vue'
const { updateEdge, addEdges } = useVueFlow();
import ChickenFootEdge from './components/ChickenFootEdge.vue';
import axios from "axios";

const modalPosition = ref({ x: 0, y: 0 });
const selectedEdge = ref(null);
const showModal = ref(false);

const TableStyle = {
  display: 'flex',
  border: '1px solid #10b981',
  background: '#007BFF',
  borderColor: '#007BFF',
  color: 'white',
  borderRadius: '5px',
  width: '500px',
  height: '60px',
  alignItems: 'center',
  justifyContent: 'space-between',
}
const RowStyle = {
  display: 'flex',
  border: '1px solid #10b981',
  borderColor: '#898989',
  background: '#ffffff',
  color: '#000000',
  borderRadius: '5px',
  width: '500px',
  height: '40px',
  alignItems: 'center',
  justifyContent: 'start',
}
const elements = ref([
  {
    id: '1',
    type: 'table',
    label: 'First table',
    data: { toolbarPosition: Position.Top, toolbarVisible: true },
    position: { x: 0, y: -100 },
    style: TableStyle,
  },
])

const addTable = () => {
  elements.value.push({
    id: Math.random().toString(),
    type: 'table',
    label: 'new table',
    data: {
      toolbarPosition: Position.Top,
      toolbarVisible: true
    },
    position: { x: 0, y: 0 },
    style: TableStyle,
  })
}

const addRow = (nodeProps) => {
  const existingRows = elements.value.filter(el => el.parentNode === nodeProps.id);
  const position = nodeProps.data.position || { x: 0, y: 0 }
  elements.value = [...elements.value, {
    id: Date.now().toString(), // use a timestamp to generate a unique id
    type: 'row', // change this to your actual row node type
    label: 'New Row',
    position: { x: position.x, y: position.y + 60 + 40 * existingRows.length },
    style: RowStyle,
    draggable: false,
    parentNode: nodeProps.id,
    data: { editing: false, showModal: false, keyMod: 'None', sqlType: 'INT', nullable: false },
  }]
}

const updateLabel = (id, newLabel) => {
  const element = elements.value.find(el => el.id === id);
  if (element) {
    element.label = newLabel;
  }
}

const updateKeyMod = (id, keyMod) => {
  const element = elements.value.find(el => el.id === id);
  if (element) {
    element.data.keyMod = keyMod;
  }
}

const toggleNullable = (id) => {
  const element = elements.value.find(el => el.id=== id);
  if (element) {
    element.data.nullable = !element.data.nullable;
  }
}

function onConnect(params) {
  params.updatable = true;
  return addEdges([params])
}

function onEdgeUpdate({ edge, connection }) {
  return updateEdge(edge, connection)
}

const onEdgeClick = (params) => {
  selectedEdge.value = params.edge;
  const edgeElement = document.querySelector(`[id="${params.edge.id}"]`);
  const edgeRect = edgeElement.getBoundingClientRect();
  modalPosition.value = {
    // Calculate the midpoint of the edge
    x: edgeRect.left + window.scrollX + edgeRect.width / 2,
    y: edgeRect.top + window.scrollY + edgeRect.height / 2
  };
  showModal.value = true;
};

//changes chicken foot marker position based on relationship type
const updateEdgeType = (relationshipType) => {
  const edgeIndex = elements.value.findIndex(el => el.id === selectedEdge.value.id);
  if (edgeIndex !== -1) {
    elements.value[edgeIndex].data.relationshipType = relationshipType;
    elements.value[edgeIndex].type = 'chickenFoot';
    if (relationshipType === 'one-to-one') {
      elements.value[edgeIndex].data.markerStart = 'none';
      elements.value[edgeIndex].data.markerEnd = 'none';
    } else if (relationshipType === 'one-to-many') {
      elements.value[edgeIndex].data.markerStart = 'none';
      elements.value[edgeIndex].data.markerEnd = 'url(#chickenFoot)';
    } else if (relationshipType === 'many-to-many') {
      elements.value[edgeIndex].data.markerStart = 'url(#chickenFoot)';
      elements.value[edgeIndex].data.markerEnd = 'url(#chickenFoot)';
    }
    showModal.value = false;
  }
};

const deleteEdge = () => {
  elements.value = elements.value.filter(el => el.id !== selectedEdge.value.id);
  showModal.value = false;
};
//deletes table or row
const deleteNode = (nodeId) => {

  const nodeToDelete = elements.value.find(el => el.id === nodeId);

  if (nodeToDelete.type === 'table') {
    elements.value = elements.value.filter(el => el.parentNode !== nodeId);
  }

  elements.value = elements.value.filter(el => el.id !== nodeId);

  if (nodeToDelete.type === 'row') {
    const siblingRows = elements.value.filter(el => el.parentNode === nodeToDelete.parentNode && el.position.y > nodeToDelete.position.y);
    siblingRows.forEach((row, index) => {
      row.position.y = nodeToDelete.position.y + 40 * index;
    });
  }
};

const exportData = async () => {
  //formatting chaotic elements array to a more civilised data array
  const links = elements.value.filter(el => el.type === 'chickenFoot');
  const nodes = elements.value.filter(elem => elem.type === 'table');
  const data = nodes.map(node => {
    const rows = elements.value.filter(row => row.parentNode === node.id);
    return {
      name: node.label,
      position: node.position, // Save the position of the node
      rows: rows.map(row => {
        return {
          id: row.id,
          label: row.label,
          position: row.position, // Save the position of the row
          keyMod: row.data.keyMod,
          sqlType: row.data.sqlType,
          nullable: row.data.nullable,
          connectedTo: links.filter(link => link.source === row.id).map(link => ({
            targetId: link.target,
            relationshipType: link.data.relationshipType
          }))
        };
      }),
    }
  });
  //this part forms the sql script string from formatted array
  let script = '';
  let primary_key_name = '';
  let primary_key_set = false;
  data.forEach((table)=>{
      script += `CREATE TABLE \`${table.name}\`( \n\t`;
      table.rows.forEach((row)=>{
        script += `\`${row.label}\` ${row.sqlType} `
        //setting modifies that persist with primary keys
        if (row.keyMod === "Primary") {
          primary_key_set = true;
          primary_key_name = row.label;
          script += "UNSIGNED ";
        }
        script += `${(row.nullable ? "NULL" : "NOT NULL")}${primary_key_set ? " AUTO_INCREMENT" : ""},\n\t`

      })
      script += ");\n"
      //setting primary key
      if(primary_key_set) {
        script += `ALTER TABLE\n\t \`${table.name}\` ADD PRIMARY KEY \`${table.name}_${primary_key_name}_primary `;
        script += `\`${primary_key_name}\``
        primary_key_set = false;
      }

  })

  console.log(script);
  console.log(data);
  // try {
  //   const response = await axios.post('http://127.0.0.1:8000/api/mysql/save', data);
  //   console.log(response.data);
  // } catch (error) {
  //   console.error(error);
  // }
}

const saveElementsToLocalStorage = () => {
  localStorage.setItem('elements', JSON.stringify(elements.value));
}

const loadElementsFromLocalStorage = () => {
  const storedElements = localStorage.getItem('elements');
  if (storedElements) {
    elements.value = JSON.parse(storedElements);
  }
}

onBeforeMount(() => {
  loadElementsFromLocalStorage();
})
onMounted(() => {
  setInterval(() => {
    localStorage.setItem('elements', JSON.stringify(elements.value));
  }, 5000);
})

provide('saveElementsToLocalStorage', saveElementsToLocalStorage);
provide('loadElementsFromLocalStorage', loadElementsFromLocalStorage);

provide('toggleNullable', toggleNullable);
provide('updateKeyMod', updateKeyMod);
provide('updateLabel', updateLabel);
provide('elements', elements)
provide('addTable', addTable)
</script>

<template>
  <button @mousedown.stop @click="exportData">Collect Data</button>
  <button @mousedown.stop @click="addTable">Add Table</button>

  <VueFlow
      :default-edge-options="{ type:'chickenFoot' }"
      @edge-update="onEdgeUpdate"
      @edge-click="onEdgeClick"
      @connect="onConnect"
      v-model="elements"
      fit-view-on-init
      class="vue-flow-basic-example"
  >
    <!--Chicken foot custom edge component -->
    <template #edge-chickenFoot="props">
      <ChickenFootEdge v-bind="props" />
    </template>

    <Background :variant="BackgroundVariant.Lines" />
    <!-- Table -->
    <template #node-table="{ id, data, label }">

      <button class="table_button" @mousedown.stop @click="addRow({ id, data, label })">
        <img class="table_icon" src="./components/icons/plus.svg" alt="Add row">
      </button>

      <input class="table_input" v-if="data.editing" :value="label" @input="updateLabel(id, $event.target.value)" @blur="data.editing = false">
      <span class="table_input"  v-else @click="data.editing = true">{{ label }}</span>

      <button class="table_button" @mousedown.stop  @click="deleteNode(id)">
        <img class="table_icon" src="./components/icons/cancel.svg" alt="Cancel">
      </button>

    </template>
    <!-- Row -->
    <template #node-row="{ id, data, label }">
      <input
          class="row_input"
          v-if="data.editing"
          :value="label"
          @input="updateLabel(id, $event.target.value)"
          @blur="data.editing = false"
          @keyup.enter="data.editing = false"
      />

      <span class="row_text" v-else @click="data.editing = true">{{ label }}</span>
      <!--SQL Type-->
      <div>
        <select v-model="data.sqlType">
          <option selected="selected" value="INT">INT</option>
          <option value="VARCHAR">VARCHAR</option>
          <option value="TEXT">TEXT</option>
          <option value="DATE">DATE</option>
        </select>
      </div>
      <!--Key mod-->
      <div>
        <select v-model="data.keyMod" @change="updateKeyMod(id, data.keyMod)">
          <option selected="selected" value="None">None</option>
          <option value="Primary">Primary</option>
          <option value="Unique">Unique</option>
          <option value="Index">Index</option>
        </select>
      </div>
      <!--Nullable-->
      <button class="table_button nullable_button" :class="{ 'nullable_on': data.nullable }" @mousedown.stop @click="toggleNullable(id)">
        N
      </button>

      <!--Delete row-->
      <button class ="table_button" @mousedown.stop  @click="deleteNode(id)">
        <img class="table_icon" src="./components/icons/cancel.svg" alt="Cancel">
      </button>

      <Handle type="source" position="right" />
      <Handle type="source" position="left" />

    </template>
  </VueFlow>
  <!--Relationship modal-->
  <div v-if="showModal" class="relationship_modal" :style="{ left: `${modalPosition.x}px`, top: `${modalPosition.y}px` }">
    <button @click="updateEdgeType('one-to-one')">One to One</button>
    <button @click="updateEdgeType('one-to-many')">One to Many</button>
    <button @click="updateEdgeType('many-to-many')">Many to Many</button>
    <button @click="deleteEdge">Delete</button>
    <button @click="showModal = false">Close</button>
  </div>

</template>

<style scoped>

.vue-flow-basic-example {
  width: 100%;
  height: 100vh;
  background-color: #f0f0f0;
}
.table_button {
  width: 15%;
  height: 80%;
  margin-top: 5px;
  padding: 0;
  border: none;
  background: none;
}
.table_icon {
  width: 70%;
  height: 70%;
  color: white;
}
.row_text{
  width: 150px;
}
.row_input{
  width: 126px;
  height: 5px;
  padding: 10px;
}
.table_input{
  width: 80%;
  padding: 10px;
}
.nullable_button.nullable_on {
  background-color: #67e867;
  color: #1b6a1b;
}
.relationship_modal {
  position: absolute;
  width: 200px;
  padding: 20px;
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.relationship_modal button {
  padding: 10px;
  border: none;
  border-radius: 5px;
  background-color: #007BFF;
  color: #fff;
  cursor: pointer;
  transition: background-color 0.3s ease;
}
.relationship_modal button:hover {
  background-color: #0056b3;
}
select {
  padding: 5px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background-color: #f9f9f9;
  color: #333;
  cursor: pointer;

}
select:hover {
  background-color: #f0f0f0;
}
.table_button.nullable_button {
  padding: 9px;
  border: none;
  border-radius: 5px;
  background-color: #dc7474; /* Red when inactive */
  color: #000; /* Black text */
  cursor: pointer;
  transition: background-color 0.3s ease;
  margin: 0;
}
.table_button.nullable_button.nullable_on {
  background-color: #67e867; /* Green when active */
}
.table_button.nullable_button:hover {

}
.table_button.nullable_button:active {
  background-color: #67e867;
}
</style>