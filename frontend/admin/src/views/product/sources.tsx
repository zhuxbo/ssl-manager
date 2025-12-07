import { ref } from "vue";
import * as productApi from "@/api/product";

export function useProductSources() {
  // 来源列表
  const sourcesList = ref([]);
  const sourcesLoading = ref(false);

  // 获取来源列表
  function getSourcesList() {
    sourcesLoading.value = true;
    productApi
      .getSourceList()
      .then(({ data }) => {
        sourcesList.value = data || [];
      })
      .catch(() => {
        sourcesList.value = [];
      })
      .finally(() => {
        sourcesLoading.value = false;
      });
  }

  return {
    sourcesList,
    getSourcesList
  };
}
