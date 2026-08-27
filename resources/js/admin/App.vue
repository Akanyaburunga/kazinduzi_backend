<script setup>
import AdminLayout from './layouts/AdminLayout.vue';
import Toasts from './components/Toasts.vue';
</script>

<template>
    <AdminLayout>
        <Toasts />
        <router-view />
    </AdminLayout>
</template>
