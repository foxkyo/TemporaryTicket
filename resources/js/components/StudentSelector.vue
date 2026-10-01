<script setup>
import { computed, onMounted, ref } from 'vue';
import { UserRound } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';

const emit = defineEmits(['update:modelValue']);

const props = defineProps({
    modelValue: {
        type: Object,
        default: null,
    },
});

const studentModalOpen = ref(false);

const studentsLoading = ref(false);
const studentsError = ref('');

const grades = ref([]);
const selectedGrade = ref('');
const selectedClass = ref('');

/*
|--------------------------------------------------------------------------
| 載入學生資料
|--------------------------------------------------------------------------
*/

const loadStudents = async () => {
    if (grades.value.length > 0) {
        return;
    }

    studentsLoading.value = true;
    studentsError.value = '';

    try {
        const response = await fetch('/api/students');

        if (!response.ok) {
            throw new Error('學生資料載入失敗');
        }

        const result = await response.json();
        const payload = Array.isArray(result)
            ? result
            : Array.isArray(result.data)
              ? result.data
              : [];

        grades.value = payload;
    } catch (error) {
        console.error(error);

        studentsError.value =
            error instanceof Error ? error.message : '學生資料載入失敗';
    } finally {
        studentsLoading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| 目前年級
|--------------------------------------------------------------------------
*/

const currentGrade = computed(() => {
    return (
        grades.value.find((item) => item.grade === selectedGrade.value) ?? null
    );
});

/*
|--------------------------------------------------------------------------
| 目前年級的班級
|--------------------------------------------------------------------------
*/

const currentClasses = computed(() => {
    return currentGrade.value?.classes ?? [];
});

/*
|--------------------------------------------------------------------------
| 目前班級
|--------------------------------------------------------------------------
*/

const currentClass = computed(() => {
    return (
        currentClasses.value.find(
            (item) => item.class === selectedClass.value,
        ) ?? null
    );
});

/*
|--------------------------------------------------------------------------
| 目前班級學生
|--------------------------------------------------------------------------
*/

const currentStudents = computed(() => {
    return currentClass.value?.students ?? [];
});

/*
|--------------------------------------------------------------------------
| 開啟 Modal
|--------------------------------------------------------------------------
*/

const openModal = async () => {
    await loadStudents();

    /*
     * 如果已有選擇學生，
     * 開啟時自動定位到該學生的年級與班級。
     */
    if (props.modelValue?.班級) {
        const className = props.modelValue.班級;

        const grade = grades.value.find((item) =>
            className.startsWith(item.grade),
        );

        if (grade) {
            selectedGrade.value = grade.grade;

            const classNameWithoutGrade = className.slice(grade.grade.length);

            const targetClass = grade.classes?.find(
                (item) => item.class === classNameWithoutGrade,
            );

            selectedClass.value =
                targetClass?.class ?? grade.classes?.[0]?.class ?? '';
        }

        return;
    }

    /*
     * 尚未選擇學生時，
     * 預設第一個年級、第一個班級。
     */
    if (!selectedGrade.value && grades.value.length > 0) {
        selectedGrade.value = grades.value[0].grade;
    }

    if (!selectedClass.value && currentClasses.value.length > 0) {
        selectedClass.value = currentClasses.value[0].class;
    }
};

/*
|--------------------------------------------------------------------------
| 切換年級
|--------------------------------------------------------------------------
*/

const changeGrade = (grade) => {
    selectedGrade.value = grade;

    selectedClass.value = currentGrade.value?.classes?.[0]?.class ?? '';
};

/*
|--------------------------------------------------------------------------
| 切換班級
|--------------------------------------------------------------------------
*/

const changeClass = (className) => {
    selectedClass.value = className;
};

/*
|--------------------------------------------------------------------------
| 選擇學生
|--------------------------------------------------------------------------
*/

const selectStudent = (student) => {
    emit('update:modelValue', student);

    studentModalOpen.value = false;
};

onMounted(() => {
    loadStudents();
});
</script>

<template>
    <div class="w-40">
        <Button
            type="button"
            variant="outline"
            class="w-full justify-start"
            @click="
                studentModalOpen = true;
                openModal();
            "
        >
            <template v-if="modelValue">
                <Badge variant="secondary" class="mr-2">
                    {{ modelValue.班級 }}
                </Badge>

                <span class="truncate">
                    {{ modelValue.姓名 }}
                </span>
            </template>

            <span v-else class="inline-flex items-center text-muted-foreground">
                <UserRound class="mr-2 h-4 w-4" />
                <span>選取學生</span>
            </span>
        </Button>

        <!-- ========================================================= -->
        <!-- Student Modal -->
        <!-- ========================================================= -->

        <Dialog v-model:open="studentModalOpen">
            <DialogContent
                class="!flex !h-[90vh] !max-h-[90vh] !w-[90vw] !max-w-[90vw] flex-col items-stretch justify-start gap-0 p-0"
            >
                <!-- Header -->

                <DialogHeader class="shrink-0 border-b px-6 py-4">
                    <DialogTitle> 選取學生 </DialogTitle>
                </DialogHeader>

                <!-- Body -->

                <div
                    class="grid min-h-0 flex-1 grid-cols-[140px_180px_minmax(0,1fr)]"
                >
                    <!-- ============================================= -->
                    <!-- 年級 -->
                    <!-- ============================================= -->

                    <div class="min-h-0 border-r">
                        <ScrollArea class="h-full">
                            <div class="space-y-1 p-3">
                                <Button
                                    v-for="item in grades"
                                    :key="item.grade"
                                    type="button"
                                    variant="ghost"
                                    class="w-full justify-start"
                                    :class="{
                                        'bg-accent text-accent-foreground':
                                            selectedGrade === item.grade,
                                    }"
                                    @click="changeGrade(item.grade)"
                                >
                                    {{ item.grade }}
                                </Button>

                                <div
                                    v-if="
                                        !studentsLoading &&
                                        grades.length === 0 &&
                                        !studentsError
                                    "
                                    class="px-2 py-6 text-center text-xs text-muted-foreground"
                                >
                                    沒有年級資料
                                </div>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- ============================================= -->
                    <!-- 班級 -->
                    <!-- ============================================= -->

                    <div class="min-h-0 border-r">
                        <ScrollArea class="h-full">
                            <div class="space-y-1 p-3">
                                <div
                                    v-if="studentsLoading"
                                    class="px-2 py-6 text-center text-xs text-muted-foreground"
                                >
                                    載入中...
                                </div>

                                <template v-else>
                                    <Button
                                        v-for="item in currentClasses"
                                        :key="item.class"
                                        type="button"
                                        variant="ghost"
                                        class="w-full justify-start"
                                        :class="{
                                            'bg-accent text-accent-foreground':
                                                selectedClass === item.class,
                                        }"
                                        @click="changeClass(item.class)"
                                    >
                                        {{ item.class }}班
                                    </Button>

                                    <div
                                        v-if="currentClasses.length === 0"
                                        class="px-2 py-6 text-center text-xs text-muted-foreground"
                                    >
                                        沒有班級資料
                                    </div>
                                </template>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- ============================================= -->
                    <!-- 學生 -->
                    <!-- ============================================= -->

                    <div class="min-h-0">
                        <ScrollArea class="h-full">
                            <div class="p-4">
                                <!-- Loading -->

                                <div
                                    v-if="studentsLoading"
                                    class="flex min-h-48 items-center justify-center text-sm text-muted-foreground"
                                >
                                    載入學生資料中...
                                </div>

                                <!-- Error -->

                                <div
                                    v-else-if="studentsError"
                                    class="flex min-h-48 items-center justify-center text-sm text-destructive"
                                >
                                    {{ studentsError }}
                                </div>

                                <template v-else>
                                    <!-- Title -->

                                    <div
                                        v-if="selectedGrade && selectedClass"
                                        class="mb-4 flex items-center justify-between"
                                    >
                                        <div>
                                            <h3 class="font-semibold">
                                                {{ selectedGrade
                                                }}{{ selectedClass }}
                                            </h3>

                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ currentStudents.length }}
                                                位學生
                                            </p>
                                        </div>
                                    </div>

                                    <!-- 未選擇年級 -->

                                    <div
                                        v-if="!selectedGrade"
                                        class="flex min-h-48 items-center justify-center text-sm text-muted-foreground"
                                    >
                                        請選擇年級
                                    </div>

                                    <!-- 未選擇班級 -->

                                    <div
                                        v-else-if="!selectedClass"
                                        class="flex min-h-48 items-center justify-center text-sm text-muted-foreground"
                                    >
                                        請選擇班級
                                    </div>

                                    <!-- 沒有學生 -->

                                    <div
                                        v-else-if="currentStudents.length === 0"
                                        class="flex min-h-48 items-center justify-center text-sm text-muted-foreground"
                                    >
                                        此班級目前沒有學生
                                    </div>

                                    <!-- 學生 -->

                                    <div
                                        v-else
                                        class="grid grid-cols-2 gap-2 sm:grid-cols-5 lg:grid-cols-10"
                                    >
                                        <Button
                                            v-for="student in currentStudents"
                                            :key="student.學號"
                                            type="button"
                                            variant="outline"
                                            class="h-auto min-h-16 min-w-0 flex-col gap-1 px-2 py-2"
                                            :class="{
                                                'border-primary bg-accent':
                                                    modelValue?.學號 ===
                                                    student.學號,
                                            }"
                                            @click="selectStudent(student)"
                                        >
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ student.座號 }}號
                                            </span>

                                            <span
                                                class="w-full truncate text-lg font-medium"
                                            >
                                                {{ student.姓名 }}
                                            </span>
                                        </Button>
                                    </div>
                                </template>
                            </div>
                        </ScrollArea>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
