export interface StorageOptions {
  namespace: string // 如 "admin-responsive-" 或 "responsive-"
}

export interface StorageInstance {
  get<T>(key: string): T | null
  set(key: string, value: any): void
  remove(key: string): void
  clear(): void
}

export function createStorage(options: StorageOptions): StorageInstance {
  const { namespace } = options

  return {
    get<T>(key: string): T | null {
      const fullKey = `${namespace}${key}`
      const value = localStorage.getItem(fullKey)
      if (!value) return null
      try {
        return JSON.parse(value) as T
      } catch {
        return value as unknown as T
      }
    },

    set(key: string, value: any): void {
      const fullKey = `${namespace}${key}`
      localStorage.setItem(fullKey, JSON.stringify(value))
    },

    remove(key: string): void {
      const fullKey = `${namespace}${key}`
      localStorage.removeItem(fullKey)
    },

    clear(): void {
      // 只清除当前命名空间的数据
      const keysToRemove: string[] = []
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i)
        if (key?.startsWith(namespace)) {
          keysToRemove.push(key)
        }
      }
      keysToRemove.forEach(key => localStorage.removeItem(key))
    }
  }
}
