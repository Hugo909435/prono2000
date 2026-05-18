<script setup>
import AuthPanel from '@/Components/AuthPanel.vue'

defineProps({
    canResetPassword: { type: Boolean },
    status:           { type: String  },
})
</script>

<template>
    <AuthPanel
        initial-view="login"
        :can-reset-password="canResetPassword"
        :status="status"
    />
</template>
