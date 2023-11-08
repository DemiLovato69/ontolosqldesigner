<script setup>
import { Handle, Position, VueFlow, useVueFlow } from '@vue-flow/core'
import { Background, BackgroundVariant } from '@vue-flow/background'
import { ref, provide} from 'vue'
const { updateEdge, addEdges } = useVueFlow();
import ChickenFootEdge from './components/ChickenFootEdge.vue';

function onConnect(params) {

  params.updatable = true;
  return addEdges([params])
}
function onEdgeUpdate({ edge, connection }) {
  return updateEdge(edge, connection)
}
const modalPosition = ref({ x: 0, y: 0 });
const selectedEdge = ref(null);
const showModal = ref(false);

const onEdgeClick = (params) => {
  selectedEdge.value = params.edge;
  const edgeElement = document.querySelector(`[id="${params.edge.id}"]`);
  const edgeRect = edgeElement.getBoundingClientRect();
  modalPosition.value = {
    x: edgeRect.left + window.scrollX,
    y: edgeRect.top + window.scrollY
  };
  showModal.value = true;
};

const updateEdgeData = (relationshipType) => {
  const edgeIndex = elements.value.findIndex(el => el.id === selectedEdge.value.id);
  if (edgeIndex !== -1) {
    elements.value[edgeIndex].data.relationshipType = relationshipType;
    // Set the edge type to 'chickenFoot' by default
    elements.value[edgeIndex].type = 'chickenFoot';
    // Add a new property 'markerStart' and 'markerEnd' to the edge data based on the relationship type
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




const TableStyle = {
  display: 'flex',
  border: '1px solid #10b981',
  background: '#ff3b25',
  borderColor: '#ff3b25',
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
  borderColor: '#ff3b25',
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
    label: 'New Table',
    data: {
      toolbarPosition: Position.Top,
      toolbarVisible: true
    },
    position: { x: 0, y: 0 },
    style: TableStyle,
  })
}
const updateLabel = (id, newLabel) => {
  const element = elements.value.find(el => el.id === id);
  if (element) {
    element.label = newLabel;
  }
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
    data: { editing: false, showModal: false, KeyMod: '', selectedSqlType: '', nullable: false },
  }]
}


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

const updateKeyMod = (id, KeyMod) => {
  const element = elements.value.find(el => el.id === id);
  if (element) {
    element.data.KeyMod = KeyMod;
  }
}
const toggleNullable = (id) => {
  const element = elements.value.find(el => el.id=== id);
  if (element) {
    element.data.nullable = !element.data.nullable;
  }
}

const collectData = () => {
  const links = elements.value.filter(el => el.type === 'smoothstep');
  const nodes = elements.value.filter(elem => elem.type === 'table');
  const data = nodes.map(node => {
    const rows = elements.value.filter(row => row.parentNode === node.id);
    return {
      table: node.label,
      rows: rows.map(row => {
        return {
          id: row.id,
          label: row.label,
          KeyMod: row.data.KeyMod,
          sqlType: row.data.selectedSqlType,
          nullable: row.data.nullable,
          connectedTo: links.filter(link => link.source === row.id).map(link => ({
            targetId: link.target,
            relationshipType: link.data.relationshipType
          }))
        };
      }),
    }
  });
  console.log(data);
}

provide('toggleNullable', toggleNullable);
provide('updateKeyMod', updateKeyMod);
provide('updateLabel', updateLabel);
provide('elements', elements)
provide('addTable', addTable)
</script>

<template>
<!--  <img class="table_icon" src="./components/icons/chicken_foot.svg" alt="Add row">-->
  <button @mousedown.stop @click="collectData">Collect Data</button>
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
    <template #edge-chickenFoot="props">
      <ChickenFootEdge v-bind="props" />
    </template>
    <Background :variant="BackgroundVariant.Lines" />

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

    <template #node-row="{ id, data, label }">
      <input  class="row_input" v-if="data.editing" :value="label" @input="updateLabel(id, $event.target.value)" @blur="data.editing = false">
      <span class="row_text" v-else @click="data.editing = true">{{ label }}</span>

      <select v-model="data.selectedSqlType">
        <option selected="selected" value="INT">INT</option>
        <option value="VARCHAR">VARCHAR</option>
        <option value="TEXT">TEXT</option>
        <option value="DATE">DATE</option>
      </select>

        <select v-model="data.KeyMod" @change="updateKeyMod(id, data.KeyMod)">
          <option selected="selected" value="None">None</option>
          <option value="Primary">Primary</option>
          <option value="Unique">Unique</option>
          <option value="Index">Index</option>
        </select>

      <button class="table_button nullable_button" :class="{ 'nullable_on': data.nullable }" @mousedown.stop @click="toggleNullable(id)">
        N
      </button>

      <button class ="table_button" @mousedown.stop  @click="deleteNode(id)">
        <img class="table_icon" src="./components/icons/cancel.svg" alt="Cancel">
      </button>

      <Handle type="source" position="right" />
      <Handle type="source" position="left" />
    </template>

  </VueFlow>
  <div v-if="showModal" class="modal" :style="{ left: `${modalPosition.x}px`, top: `${modalPosition.y}px` }">
    <h2>Select Relationship Type</h2>
    <button @click="updateEdgeData('one-to-one')">One to One</button>
    <button @click="updateEdgeData('one-to-many')">One to Many</button>
    <button @click="updateEdgeData('many-to-many')">Many to Many</button>
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
}
select{
  width: 70px;
}
.row_text{
  width: 150px;
}
.row_input{
  width: 125px;
}
.table_input{
  width: 80%;
}
input {
  padding: 0.5rem;
  font-size: 1rem;
  color: #000;
  background-color: #fff;
  border: none;
}
.nullable_button {
  background-color: #ff0000;
  color: #ffffff;
}

.nullable_button.nullable_on {
  background-color: #00ff00;
}
.modal {
  position: absolute;
}
</style>