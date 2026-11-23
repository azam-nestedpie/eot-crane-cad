<script setup>
import { nextTick, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    request: { type: Object, default: null },
});
const emit = defineEmits(['answer']);
const confirmBtn = ref(null);

watch(
    () => props.request,
    async (r) => {
        if (r) {
            await nextTick();
            confirmBtn.value?.focus();
        }
    },
);
</script>

<template>
    <Transition name="fade">
        <div
            v-if="request"
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/50 p-4 backdrop-blur-sm"
            @click.self="emit('answer', false)"
            @keydown.esc="emit('answer', false)"
        >
            <div class="card w-full max-w-sm p-6" role="alertdialog" aria-modal="true" :aria-label="request.title">
                <div class="flex gap-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="request.danger ? 'bg-red-50 text-red-600' : 'bg-crane-100 text-crane-600'"
                    >
                        <Icon :name="request.danger ? 'trash' : 'alert'" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ request.title }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ request.message }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn btn-soft" @click="emit('answer', false)">Cancel</button>
                    <button
                        ref="confirmBtn"
                        type="button"
                        class="btn"
                        :class="request.danger ? 'bg-red-600 text-white hover:bg-red-700' : 'btn-primary'"
                        @click="emit('answer', true)"
                    >
                        {{ request.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
