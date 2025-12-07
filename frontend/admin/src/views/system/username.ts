import { ref, h } from "vue";

// 用户信息卡片的全局状态
export const showUserInfoCard = ref(false);
export const selectedUserId = ref<number | null>(null);

// 创建用户名点击处理函数
export const createUsernameClickHandler = () => {
  const handleUsernameClick = (user: any) => {
    // 获取用户ID，支持多种数据结构
    let userId = null;

    if (user.id) {
      userId = user.id;
    } else if (user.user_id) {
      userId = user.user_id;
    } else if (user.user?.id) {
      userId = user.user.id;
    }

    if (userId) {
      selectedUserId.value = userId;
      showUserInfoCard.value = true;
    }
  };

  return { handleUsernameClick };
};

// 创建用户名列的渲染器
export const createUsernameRenderer = (userField = "username") => {
  const { handleUsernameClick } = createUsernameClickHandler();

  return (data: any) => {
    const { row } = data;

    const handleClick = (e: Event) => {
      e.stopPropagation();

      // 获取用户对象或当前行数据
      let userObject = row;

      // 如果是嵌套字段（如 user.username），尝试获取用户对象
      if (userField.includes(".")) {
        const pathParts = userField.split(".");
        if (pathParts[0] === "user" && row.user) {
          userObject = row.user;
        }
      }

      handleUsernameClick(userObject);
    };

    const displayValue = userField.includes(".")
      ? userField.split(".").reduce((obj, key) => obj?.[key], row)
      : row[userField];

    return h(
      "span",
      {
        class: "cursor-pointer",
        onClick: handleClick
      },
      displayValue
    );
  };
};
