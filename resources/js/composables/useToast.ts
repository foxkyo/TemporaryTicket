import { ref } from 'vue'

type ToastType = 'success' | 'error' | 'info'

interface ToastState {
    show: boolean
    message: string
    type: ToastType
}

const toast = ref<ToastState>({
    show: false,
    message: '',
    type: 'success',
})

let timeout: number | null = null

export function useToast() {
    const showToast = (message: string, type: ToastType = 'success') => {
        toast.value = { show: true, message, type }

        if (timeout) {
            clearTimeout(timeout)
        }

        timeout = window.setTimeout(() => {
            toast.value.show = false
        }, 3000)
    }

    const closeToast = () => {
        if (timeout) {
            clearTimeout(timeout)
            timeout = null
        }
        toast.value.show = false
    }

    return {
        toast,
        showToast,
        closeToast,
    }
}
