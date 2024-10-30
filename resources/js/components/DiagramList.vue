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
                <a @click.prevent="viewDiagram(diagram.id)">{{ diagram.name }}</a>
            </li>
        </ul>
    </div>
</template>

<script>
export default {
    props: {
        diagrams: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            diagrams: this.diagrams,
            newDiagramName: '',
        };
    },
    methods: {
        viewDiagram(id) {
            window.location.href = `/diagrams/${id}`;
        },
        addDiagram() {

            if (!this.newDiagramName.trim()) return;

            fetch('/api/diagrams', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ name: this.newDiagramName })
            })
                .then(response => response.json())
                .then(data => {
                    this.diagrams.push(data);
                    this.newDiagramName = '';
                })
                .catch(error => console.error('Error adding diagram:', error));
        },
    },
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
    margin: 10px 0;
}

.diagram-item a {
    text-decoration: none;
    color: #007bff;
    transition: color 0.3s;
}

.diagram-item a:hover {
    color: #0056b3;
}
</style>
