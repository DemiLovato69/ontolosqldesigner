<template>
    <div class="centered-container">
        <div class="form-container">
            <h2 class="form-title">Register</h2>

            <form @submit.prevent="register">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        v-model="userData.email"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        v-model="userData.password"
                        required
                    />
                </div>

                <button type="submit" class="button button-primary">Submit</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { Auth } from "../services/Auth";

const userData = ref({
    email: '',
    password: ''
});

const register = async () => {
    await Auth.register(userData.value);
};
</script>
