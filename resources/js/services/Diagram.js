import axios from "axios";
import {useToast} from 'vue-toast-notification';
const $toast = useToast();

export const Diagram = {

    async get (id) {
        try {
            const response = await axios.post(`/api/diagrams/${id}`, {
                id: id
            });
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

    async save(id, schema) {

        try {
            const response = await axios.put(`/api/diagrams/${id}`, {
                id: id,
                schema: JSON.stringify(schema)
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
