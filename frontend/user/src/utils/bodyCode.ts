let appended = false;

/** 管理员配置的可信代码仅在启动完成后执行一次。 */
export const appendBodyCode = async (code?: string): Promise<void> => {
  if (appended || !code?.trim()) return;
  appended = true;

  const template = document.createElement("template");
  template.innerHTML = code;
  // template 中的 script 不会执行，挂载内容后用新 script 激活。
  const scripts = Array.from(template.content.querySelectorAll("script"));
  document.body.append(template.content);
  for (const original of scripts) {
    const script = document.createElement("script");
    script.async = false;
    for (const attribute of Array.from(original.attributes)) {
      script.setAttribute(attribute.name, attribute.value);
    }
    script.textContent = original.textContent;
    const blocking =
      script.hasAttribute("src") &&
      !script.hasAttribute("async") &&
      ["", "text/javascript", "application/javascript", "module"].includes(
        script.type
      );
    const loaded = blocking
      ? new Promise<void>(resolve => {
          script.addEventListener("load", () => resolve(), { once: true });
          script.addEventListener("error", () => resolve(), { once: true });
        })
      : undefined;
    original.replaceWith(script);
    if (loaded) await loaded;
  }
};
