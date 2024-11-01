import axios from "axios";
import {useToast} from 'vue-toast-notification';
import {computed} from "vue";
import store from "../store";
const $toast = useToast();

export const Diagram = {

    async addDiagram(diagramName) {
        try {
            const response = await axios.post('/api/add-diagram', {
                name: diagramName
            });
            $toast.success(response.data.message);
        }
        catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!')
            }
        }
    },
    async selectDiagram (id, name, store) {
        try {
            const response = await axios.post('/api/select-diagram', {
                id: id
            });
            store.commit('setCurrentDiagramId', id);
            store.commit('setCurrentDiagramName', name);
            return response.data;
        }
        catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!')
            }
        }
    },

    async getDiagrams() {
        try {
            const response = await axios.get('/api/get-diagrams');
            return response.data;
        }
        catch (error) {
            if (error.response) {
                $toast.error('Failed to get diagrams!');
            }
            else {
                $toast.error('Something went wrong!')
            }
        }
    },
    async saveDiagram(id, diagram) {
        try {
            const response = await axios.put(`/api/diagrams/${id}`, {
                id: id,
                diagram: diagram
            });
            $toast.success(response.data.message)
        } catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!')
            }
        }
    }



}
