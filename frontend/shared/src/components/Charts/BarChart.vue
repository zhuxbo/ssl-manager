<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from "vue";
import * as echarts from "echarts/core";
import { BarChart } from "echarts/charts";
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent
} from "echarts/components";
import { CanvasRenderer } from "echarts/renderers";
import type { EChartsCoreOption } from "echarts/core";

// 注册ECharts组件
echarts.use([
  BarChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent,
  CanvasRenderer
]);

interface SeriesData {
  name: string;
  data: number[];
  color?: string;
  type?: "bar";
}

interface Props {
  title?: string;
  xAxisData: string[];
  series: SeriesData[];
  height?: string;
  horizontal?: boolean;
  showLegend?: boolean;
  barWidth?: string | number;
}

const props = withDefaults(defineProps<Props>(), {
  title: "",
  height: "350px",
  horizontal: false,
  showLegend: true,
  barWidth: "60%"
});

const chartRef = ref<HTMLDivElement>();
let chartInstance: echarts.ECharts | null = null;

const defaultColors = [
  "#3B82F6",
  "#10B981",
  "#F59E0B",
  "#EF4444",
  "#8B5CF6",
  "#EC4899"
];

const initChart = () => {
  if (!chartRef.value) return;

  chartInstance = echarts.init(chartRef.value);

  const option: EChartsCoreOption = {
    title: {
      text: props.title,
      left: "center",
      textStyle: {
        fontSize: 16,
        fontWeight: "normal",
        color: "#374151"
      }
    },
    tooltip: {
      trigger: "axis",
      axisPointer: {
        type: "shadow"
      },
      confine: true,
      textStyle: {
        fontSize: 12
      }
    },
    legend: props.showLegend
      ? {
          data: props.series.map(s => s.name),
          bottom: 10,
          textStyle: {
            color: "#6B7280"
          }
        }
      : undefined,
    grid: {
      left: props.horizontal ? 200 : "3%",
      right: "4%",
      bottom: props.showLegend ? "15%" : "10%",
      top: props.title ? "15%" : "10%",
      containLabel: false
    },
    xAxis: {
      type: props.horizontal ? "value" : "category",
      data: props.horizontal ? undefined : props.xAxisData,
      axisLabel: {
        color: "#6B7280"
      },
      axisLine: {
        lineStyle: {
          color: "#E5E7EB"
        }
      }
    },
    yAxis: {
      type: props.horizontal ? "category" : "value",
      data: props.horizontal ? props.xAxisData : undefined,
      axisLabel: {
        color: "#6B7280",
        width: props.horizontal ? 190 : undefined,
        overflow: props.horizontal ? "truncate" : undefined,
        fontSize: 11,
        lineHeight: 14,
        interval: 0,
        align: props.horizontal ? "right" : undefined,
        verticalAlign: props.horizontal ? "middle" : undefined,
        padding: props.horizontal ? [0, 5, 0, 0] : undefined
      },
      axisLine: {
        lineStyle: {
          color: "#E5E7EB"
        }
      },
      splitLine: {
        lineStyle: {
          color: "#F3F4F6"
        }
      }
    },
    series: props.series.map((seriesItem, index) => ({
      name: seriesItem.name,
      type: "bar",
      data: seriesItem.data,
      barWidth: props.barWidth,
      itemStyle: {
        color: seriesItem.color || defaultColors[index % defaultColors.length],
        borderRadius: props.horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]
      },
      emphasis: {
        itemStyle: {
          shadowBlur: 10,
          shadowOffsetX: 0,
          shadowColor: "rgba(0, 0, 0, 0.3)"
        }
      }
    }))
  };

  chartInstance.setOption(option);
};

const resizeChart = () => {
  if (chartInstance) {
    chartInstance.resize();
  }
};

onMounted(() => {
  initChart();
  window.addEventListener("resize", resizeChart);
});

onBeforeUnmount(() => {
  if (chartInstance) {
    chartInstance.dispose();
  }
  window.removeEventListener("resize", resizeChart);
});

// 监听数据变化
watch(
  () => [props.xAxisData, props.series],
  () => {
    if (chartInstance) {
      initChart();
    }
  },
  { deep: true }
);
</script>

<template>
  <div ref="chartRef" :style="{ height: height, width: '100%' }" />
</template>

<style scoped>
/* 图表容器样式 */
</style>
