<script setup>
import { Handle, Position, VueFlow, useVueFlow } from '@vue-flow/core'
import { Background, BackgroundVariant } from '@vue-flow/background'
import { ref, provide,computed, onMounted, onBeforeMount} from 'vue'
const { updateEdge, addEdges } = useVueFlow();
import ChickenFootEdge from '../components/ChickenFootEdge.vue';

import { addRowS, addTableS, updateConnectionLineTypeS, deleteNodeS, deleteEdgeS } from '../services/TableActions.js';

const modalPosition = ref({ x: 0, y: 0 });
const selectedEdge = ref(null);
const showRelationshipModal = ref(false);

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
    id: '1',
    type: 'table',
    label: 'First table',
    data: { toolbarPosition: Position.Top, toolbarVisible: true },
    position: { x: 0, y: -100 },
    style: TableStyle,
  },
])

const addTable = () => {
  addTableS(elements, TableStyle);
}
const addRow = (nodeProps) => {
  addRowS(elements, nodeProps);
};
const deleteEdge = () => {
  deleteEdgeS(elements, selectedEdge);
};
const deleteNode = (nodeId) => {
  deleteNodeS(elements, nodeId);
};
const updateConnectionLineType = (relationshipType) => {
  updateConnectionLineTypeS(elements, selectedEdge, relationshipType);
};

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

const openRelationshipModal = (params) => {
  selectedEdge.value = params.edge;
  const edgeElement = document.querySelector(`[id="${params.edge.id}"]`);
  const edgeRect = edgeElement.getBoundingClientRect();
  modalPosition.value = {
    // Calculate the midpoint of the edge
    x: edgeRect.left + window.scrollX + edgeRect.width / 2,
    y: edgeRect.top + window.scrollY + edgeRect.height / 2
  };
  showRelationshipModal.value = true;
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
          unsigned: row.data.unsigned,
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
  let primary_key = false;
  let auto_increment_added = false;
  data.forEach((table)=>{
    script += `CREATE TABLE \`${table.name}\`( \n\t`;
    table.rows.forEach((row)=>{
      script += `\`${row.label}\` ${row.sqlType} `
      //setting modifies that persist with primary keys
      if (row.keyMod === "Primary") {
        primary_key = true;
        primary_key_name = row.label;
        script += "UNSIGNED ";
      }
      script += `${(row.nullable ? "NULL" : "NOT NULL")}`;

      if(primary_key){
        script += " AUTO_INCREMENT";
        primary_key = false;
        auto_increment_added = true;
      }
      script+= ",\n\t"
    })
    script += ");\n"
    //setting primary key
    if(auto_increment_added) {
      script += `ALTER TABLE\n\t \`${table.name}\` ADD PRIMARY KEY \`${table.name}_${primary_key_name}_primary\` `;
      script += `(\`${primary_key_name}\`);\n`
      auto_increment_added = false;
      primary_key = false
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

const toggleOptionsModal = (id, event) => {
  const row = elements.value.find(el => el.id === id);
  const offsetX = 350;

  const documentX = row.position.x;
  const documentY = row.position.y;

  const rowHeight = 60;
  const rowIndex = elements.value.findIndex(el => el.id === id);
  const offsetY = rowIndex * (rowHeight-20);

  row.data.modalPosition = { x: documentX + offsetX, y: documentY - offsetY };
  row.data.showOptionsModal = !row.data.showOptionsModal;
};
const toggleUnsigned = (id) => {
  const element = elements.value.find(el => el.id === id);
  if (element) {
    element.data.unsigned = !element.data.unsigned;
  }
};



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
provide('toggleOptionsModal', toggleOptionsModal);
provide('toggleUnsigned', toggleUnsigned);
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
      @edge-click="openRelationshipModal"
      @connect="onConnect"
      v-model="elements"
      fit-view-on-init
      :zoomOnDoubleClick = false

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
        <img class="table_icon" src="../components/icons/plus.svg" alt="Add row">
      </button>

      <input class="table_input" v-if="data.editing" :value="label" @input="updateLabel(id, $event.target.value)" @blur="data.editing = false">
      <span class="table_input"  v-else @click="data.editing = true">{{ label }}</span>

      <button class="table_button" @mousedown.stop  @click="deleteNode(id)">
        <img class="table_icon" src="../components/icons/cancel.svg" alt="Cancel">
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



      <!--Options-->
      <button class="table_button" @mousedown.stop @click="toggleOptionsModal(id, $event)">
        <img class="table_icon" src="../components/icons/dots.svg" alt="More options">
      </button>

      <!-- Options modal -->
      <div v-if="data.showOptionsModal" class="options_modal" :style="{ left: `${data.modalPosition.x}px`, top: `${data.modalPosition.y}px` }">
        <!--Key mod-->
        <select v-model="data.keyMod" @change="updateKeyMod(id, data.keyMod)">
          <option selected="selected" value="None">None</option>
          <option value="Primary">Primary</option>
          <option value="Unique">Unique</option>
          <option value="Index">Index</option>
        </select>
        <p class="modal_text">Unsigned</p>
        <input type="checkbox"  @mousedown.stop :checked="data.unsigned" @change="toggleUnsigned(id)">
        <p class="modal_text">Nullable</p>
        <input type="checkbox" @mousedown.stop :checked="data.nullable" @change="toggleNullable(id)">
        <!--        <button @click="showOptionsModal = false">Close</button>-->
      </div>
      <!--Delete row-->
      <button class ="table_button" @mousedown.stop  @click="deleteNode(id)">
        <img class="table_icon" src="../components/icons/cancel.svg" alt="Cancel">
      </button>

      <Handle type="source" position="right" />
      <Handle type="source" position="left" />

    </template>

  </VueFlow>
  <!--Relationship modal-->
  <div v-if="showRelationshipModal" class="relationship_modal" :style="{ left: `${modalPosition.x}px`, top: `${modalPosition.y}px` }">
    <button @click="updateConnectionLineType('one-to-one')">One to One</button>
    <button @click="updateConnectionLineType('one-to-many')">One to Many</button>
    <button @click="updateConnectionLineType('many-to-many')">Many to Many</button>
    <button @click="deleteEdge">Delete</button>
    <button @click="showRelationshipModal = false">Close</button>
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
input{
  margin :0;
}
.options_modal{
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid #007BFF;
  border-radius: 5px;
  width: 300px;
}
.modal_text{
  margin : 0;
  font-size: 15px;

}
</style>