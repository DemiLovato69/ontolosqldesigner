<template>
    <div>
        <header>
            <nav>
                <router-link to="/">Home</router-link>
                <router-link to="/login">Login</router-link>
                <router-link to="/register">Register</router-link>
                <router-link to="/diagrams">Diagrams</router-link>
            </nav>
        </header>


        <router-view />
    </div>
</template>

<script>
export default {
    name: "App",
};
</script>

<style scoped>
/* Optional styling */
</style>
