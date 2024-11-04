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

        <router-view></router-view>
    </div>
</template>
