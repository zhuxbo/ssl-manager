/**
 * 表单验证模块
 * @version 1.0.0
 */
window.Validator = (function () {
  "use strict";

  // 域名验证正则（支持中文等国际化域名）
  const domainRegex =
    /^(?:\*\.)?(?:[a-zA-Z0-9\u00a1-\uffff](?:[a-zA-Z0-9\u00a1-\uffff-]{0,61}[a-zA-Z0-9\u00a1-\uffff])?\.)*[a-zA-Z0-9\u00a1-\uffff](?:[a-zA-Z0-9\u00a1-\uffff-]{0,61}[a-zA-Z0-9\u00a1-\uffff])?$/;

  // 邮箱验证正则
  const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

  // IP地址验证正则
  const ipRegex =
    /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

  // 验证订单号
  function validateTid(value) {
    value = value ? value.trim() : "";
    if (!value) {
      return "请输入订单号";
    }
    // 与后端/easy 保持一致，最小长度为 3
    if (value.length < 3) {
      return "订单号格式不正确";
    }
    return null;
  }

  // 验证邮箱
  function validateEmail(value) {
    value = value ? value.trim() : "";
    if (!value) {
      return "请输入邮箱地址";
    }
    if (!emailRegex.test(value)) {
      return "请输入有效的邮箱地址";
    }
    return null;
  }

  // 验证域名
  function validateDomain(value, validationMethod, isWildcard) {
    value = value ? value.trim() : "";
    if (!value) {
      return "请输入域名";
    }

    // 清理域名
    value = value.toLowerCase();

    // 检查是否是IP地址
    if (isIP(value)) {
      if (!["file", "http", "https"].includes(validationMethod)) {
        return "IP地址只能使用文件验证方式";
      }
      return null;
    }

    // 关于 *.www. 模式：不在此处直接拦截，交由提交时的确认弹窗处理
    // if (value.includes('*.www.')) {
    //     return '通配符证书不需要包含 www 子域名';
    // }

    // 通配符域名检查
    if (isWildcard) {
      if (!value.startsWith("*.")) {
        value = "*." + value;
      }
    } else {
      if (value.startsWith("*.")) {
        return "该产品不支持通配符域名";
      }
    }

    // 验证域名格式
    if (!domainRegex.test(value)) {
      return "请输入有效的域名";
    }

    // 检查域名层级
    const parts = value.replace("*.", "").split(".");
    if (parts.length < 2) {
      return "请输入完整的域名（如：example.com）";
    }

    return null;
  }

  // 验证验证方式
  function validateMethod(value) {
    if (!value || value.trim() === "") {
      return "请选择验证方式";
    }
    return null;
  }

  // 检查是否是IP地址
  function isIP(value) {
    return ipRegex.test(value);
  }

  // 清理域名输入
  function cleanDomain(value) {
    return value
      .replaceAll(" ", "")
      .replaceAll("　", "")
      .replaceAll("\t", "")
      .toLowerCase();
  }

  // 显示错误信息
  function showError(inputId, message) {
    const input = document.getElementById(inputId);
    const errorElement = document.getElementById(inputId + "-error");

    if (input && errorElement) {
      const formGroup = input.closest(".form-group");
      if (message) {
        formGroup.classList.add("error");
        errorElement.textContent = message;
        errorElement.classList.add("show");
      } else {
        formGroup.classList.remove("error");
        errorElement.textContent = "";
        errorElement.classList.remove("show");
      }
    }
  }

  // 清除所有错误
  function clearErrors(formId) {
    const form = document.getElementById(formId);
    if (form) {
      form.querySelectorAll(".form-group").forEach((group) => {
        group.classList.remove("error");
      });
      form.querySelectorAll(".error-message").forEach((error) => {
        error.textContent = "";
        error.classList.remove("show");
      });
    }
  }

  // 验证表单
  function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    clearErrors(formId);
    let isValid = true;

    // 根据表单ID执行不同的验证
    if (formId === "tid-form") {
      const tid = form.tid.value.trim();
      const error = validateTid(tid);
      if (error) {
        showError("tid", error);
        isValid = false;
      }
    } else if (formId === "apply-form") {
      // 验证邮箱
      const email = form.email.value.trim();
      const emailError = validateEmail(email);
      if (emailError) {
        showError("email", emailError);
        isValid = false;
      }

      // 如果域名输入框存在，验证域名
      const domainInput = form.domain;
      if (domainInput && !domainInput.disabled) {
        const domain = domainInput.value.trim();
        const method = form.validation_method.value;
        const isWildcard = window.appState?.product?.is_wildcard;

        const domainError = validateDomain(domain, method, isWildcard);
        if (domainError) {
          showError("domain", domainError);
          isValid = false;
        }

        const methodError = validateMethod(method);
        if (methodError) {
          showError("validation_method", methodError);
          isValid = false;
        }
      }
    }

    return isValid;
  }

  return {
    validateTid,
    validateEmail,
    validateDomain,
    validateMethod,
    isIP,
    cleanDomain,
    showError,
    clearErrors,
    validateForm,
  };
})();
