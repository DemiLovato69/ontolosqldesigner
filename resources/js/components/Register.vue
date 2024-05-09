<template>

    <button class="btn btn-primary" @click="open = true">Register</button>

    <Teleport to="#app">

        <div class="modal flex-centered" v-if="open">

        <div class="modal-content">

            <div class="flex-centered modal-header">

                <div class="modal-title">
                    <h5>Register</h5>
                </div>

                <div class="close-button">
                    <button type="button"  @click="open = false">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

            </div>

            <div class="modal-body">

                <form @submit.prevent="register">

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

const open = ref(false);

const register = async () => {
  Auth.register(userData);
};
const userData = {
    email: '',
    password: ''
}

</script>

<style scoped>

</style>
