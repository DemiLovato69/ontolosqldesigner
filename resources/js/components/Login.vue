<template>

    <button class="btn btn-primary" @click="open = true">Login</button>

    <Teleport to="#app">

        <div class="modal flex-centered" v-if="open">

            <div class="modal-content">

                <div class="flex-centered modal-header">

                    <div class="modal-title">
                        <h5>Login</h5>
                    </div>

                    <div class="close-button">
                        <button type="button"  @click="open = false">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                </div>

                <div class="modal-body">

                    <form @submit.prevent="login">

                        <div class="form-group">
                            <input type="email" class="form-control" id="email" v-model="userData.email">
                        </div>

                        <div class="form-group">
                            <input type="password" class="form-control" id="password" v-model="userData.password">
                        </div>

                        <button type="submit" class="btn btn-primary">Submit</button>

                    </form>

                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import {ref} from "vue";
import {Auth} from "../services/Auth";
import { useStore } from 'vuex';


const open = ref(false);
const store = useStore();

const userData = {
    email: '',
    password: ''
}
const login = async () => {
    await Auth.login(userData, store);
    open.value = false;
};
</script>

<style scoped>

</style>
