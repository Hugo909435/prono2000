<script setup>
import { computed } from 'vue';

const props = defineProps({
    flag: String,
    size: {
        type: String,
        default: 'md' // sm, md, lg
    }
});

const isUrl = computed(() => {
    return props.flag && (props.flag.startsWith('http://') || props.flag.startsWith('https://'));
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: { img: 'w-5 h-4', emoji: 'text-base' },
        md: { img: 'w-6 h-5', emoji: 'text-lg' },
        lg: { img: 'w-8 h-6', emoji: 'text-2xl' },
    };
    return sizes[props.size] || sizes.md;
});
</script>

<template>
    <span v-if="!flag" class="text-gray-300">-</span>
    <img
        v-else-if="isUrl"
        :src="flag"
        :class="[sizeClasses.img, 'inline-block object-cover rounded-sm']"
        alt="flag"
    />
    <span v-else :class="sizeClasses.emoji">{{ flag }}</span>
</template>
