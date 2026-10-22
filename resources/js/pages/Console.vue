<script setup lang="ts">
import { ref } from "vue";
import Tabs from "@/components/ui/tabs/Tabs.vue";
import TabsList from "@/components/ui/tabs/TabsList.vue";
import TabsTrigger from "@/components/ui/tabs/TabsTrigger.vue";
import TabsContent from "@/components/ui/tabs/TabsContent.vue";
import VehicleManagement from "@/components/VehicleManagement.vue";
import DailyTicketReport from "@/components/DailyTicketReport.vue";

const activeTab = ref("vehicle-management");
</script>

<template>
    <div class="min-h-screen bg-slate-100 p-6 text-slate-800">
        <div class="mx-auto max-w-6xl">
            <div
                class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"
            >
                <h1 class="text-2xl font-bold">管理後台</h1>
            </div>

            <Tabs v-model="activeTab" default-value="vehicle-management">
                <TabsList>
                    <TabsTrigger value="vehicle-management"
                        >車別管理</TabsTrigger
                    >
                    <TabsTrigger value="daily-report">日報表</TabsTrigger>
                </TabsList>

                <TabsContent value="vehicle-management">
                    <VehicleManagement />
                </TabsContent>

                <TabsContent value="daily-report">
                    <DailyTicketReport />
                </TabsContent>
            </Tabs>
        </div>
    </div>
</template>
