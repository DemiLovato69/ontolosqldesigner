<template>
    <div class="diagram-window">
        <h2 class="diagram-title">Diagrams</h2>

        <div class="add-diagram">
            <input
                type="text"
                v-model="newDiagramName"
                placeholder="Enter diagram name"
                class="diagram-input"
            />
            <button @click="addDiagram" class="add-diagram-button">Add Diagram</button>
        </div>

        <ul class="diagram-list">
            <li v-for="diagram in diagrams" :key="diagram.id" class="diagram-item">

                <span @click="viewDiagram(diagram.id)" class="icon view-icon">📄</span>

                <input
                    type="text"
                    v-model="diagram.name"
                    @focus="backupName(diagram)"
                    @change="updateDiagram(diagram)"
                    class="diagram-name-input"
                />

                <span @click="deleteDiagram(diagram.id)" class="icon delete-icon">🗑️</span>
            </li>
        </ul>
    </div>
</template>

<script>
import axios from "axios";
import {useToast} from "vue-toast-notification";
import router from "../router/index.js";

const $toast = useToast();

export default {

    data() {
        return {
            diagrams: [],
            newDiagramName: '',
            originalName: null,
        };
    },
    methods: {
        viewDiagram(id) {
            router.push({path:`/diagrams/${id}`});
        },
        async addDiagram() {
            try {
                const response = await axios.post('/api/diagrams', {
                    name: this.newDiagramName
                });
                this.newDiagramName = '';
                await this.fetchDiagrams();
                $toast.success(response.data.message);
            } catch (error) {
                if (error.response) {
                    $toast.error(error.response.data.message);
                } else {
                    $toast.error('Something went wrong!');
                }
            }
        },
        async updateDiagram(diagram) {
            try {
                await axios.put(`/api/diagrams/${diagram.id}`, {name: diagram.name});
                await this.fetchDiagrams();
                $toast.success('Diagram name updated successfully');
            } catch (error) {
                if (error.response) {
                    $toast.error(error.response.data.message);
                } else {
                    $toast.error('Something went wrong!');
                }
                diagram.name = this.originalName;
            } finally {
                this.originalName = null;
            }
        },
        async deleteDiagram(id) {
            try {
                const response = await axios.delete(`/api/diagrams/${id}`);
                await this.fetchDiagrams();
                $toast.success(response.data.message);
            } catch (error) {
                if (error.response) {
                    $toast.error(error.response.data.message);
                } else {
                    $toast.error('Something went wrong!');
                }
            }
        },
        backupName(diagram) {
            this.originalName = diagram.name;
        },
        async fetchDiagrams() {
            try {
                const response = await axios.get(`/api/diagrams`);
                this.diagrams = response.data;
            } catch (error) {
                if (error.response) {
                    $toast.error(error.response.data.message);
                } else {
                    $toast.error('Something went wrong!');
                }
            }
        },
    },
    created() {
        this.fetchDiagrams();
    }
};
</script>

<style scoped>

.diagram-window {
    background-color: #f9f9f9;
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    margin: 20px auto;
}

.diagram-title {
    font-size: 24px;
    margin-bottom: 15px;
}

.diagram-list {
    list-style-type: none;
    padding: 0;
}

.diagram-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 10px 0;
}

.icon {
    cursor: pointer;
    margin: 0 8px;
    font-size: 20px;
}

.view-icon {
    color: #007bff;
    transition: color 0.3s;
}

.view-icon:hover {
    color: #0056b3;
}

.delete-icon {
    color: #dc3545;
    transition: color 0.3s;
}

.delete-icon:hover {
    color: #c82333;
}

.diagram-name-input {
    flex-grow: 1;
    border: none;
    background-color: transparent;
    font-size: 16px;
    color: #333;
    padding: 4px 0;
}

.diagram-name-input:focus {
    outline: none;
    border-bottom: 1px solid #007bff;
}
</style>
