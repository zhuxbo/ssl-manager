// 切换显示/隐藏扩展列表
function toggleExtensions() {
    var list = document.getElementById("extensions-list");
    var toggleText = document.getElementById("extensions-toggle-text");
    
    if (list.style.display === "none") {
        list.style.display = "block";
        if (toggleText) toggleText.textContent = "折叠";
    } else {
        list.style.display = "none";
        if (toggleText) toggleText.textContent = "展开";
    }
}

// 切换显示/隐藏权限列表
function togglePermissions() {
    var list = document.getElementById("permissions-list");
    var toggleText = document.getElementById("permissions-toggle-text");
    
    if (list.style.display === "none") {
        list.style.display = "block";
        if (toggleText) toggleText.textContent = "折叠";
    } else {
        list.style.display = "none";
        if (toggleText) toggleText.textContent = "展开";
    }
}

// 切换显示/隐藏必需PHP函数列表
function toggleRequiredFunctions() {
    var list = document.getElementById("required-functions-list");
    var toggleText = document.getElementById("required-functions-toggle-text");
    
    if (list.style.display === "none") {
        list.style.display = "block";
        if (toggleText) toggleText.textContent = "折叠";
    } else {
        list.style.display = "none";
        if (toggleText) toggleText.textContent = "展开";
    }
}

// 切换显示/隐藏可选PHP函数列表
function toggleOptionalFunctions() {
    var list = document.getElementById("optional-functions-list");
    var toggleText = document.getElementById("optional-functions-toggle-text");
    
    if (list.style.display === "none") {
        list.style.display = "block";
        if (toggleText) toggleText.textContent = "折叠";
    } else {
        list.style.display = "none";
        if (toggleText) toggleText.textContent = "展开";
    }
}

// 切换显示/隐藏PHP函数列表 (向后兼容)
function toggleFunctions() {
    toggleRequiredFunctions();
    toggleOptionalFunctions();
}

// 切换显示/隐藏整个系统环境检查
function toggleSystemCheck() {
    var details = document.getElementById("system-check-details");
    var summary = document.getElementById("system-check-summary");
    var toggleText = document.getElementById("system-check-toggle-text");
    
    if (details && summary && toggleText) {
        if (details.style.display === "none") {
            details.style.display = "block";
            summary.style.display = "none";
            toggleText.textContent = "折叠";
        } else {
            details.style.display = "none";
            summary.style.display = "block";
            toggleText.textContent = "展开";
        }
    }
}

// 准备并提交安装表单
function prepareAndSubmitInstall(button) {
    button.disabled = true;
    button.innerHTML = '<span class="loading-spinner"></span> 正在安装...';
    button.style.background = '#9ca3af';
    button.style.cursor = 'not-allowed';

    // 显示日志框
    var logDiv = document.getElementById("install-log-div");
    if (logDiv) {
        logDiv.style.display = "block";
        logDiv.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <div class="progress-ring">
                    <div class="progress-ring-fill"></div>
                </div>
                <div style="margin-top: 15px; color: #e2e8f0;">正在准备安装...</div>
            </div>
        `;
    }

    var form = button.form;
    if (form) {
        form.submit();
    } else {
        console.error("找不到安装按钮的表单");
    }
}

// 处理安装日志中的HTML转义
function decodeHtmlEntities(text) {
    var textArea = document.createElement("textarea");
    textArea.innerHTML = text;
    return textArea.value;
}

// 当文档加载完成时运行
document.addEventListener("DOMContentLoaded", function () {
    // 如果页面有错误信息，自动滚动到错误位置
    var errorSection = document.getElementById("error-section");
    if (errorSection) {
        setTimeout(function() {
            errorSection.scrollIntoView({ 
                behavior: "smooth", 
                block: "center" 
            });
            // 高亮显示错误区域
            errorSection.style.animation = "highlight 1s ease-in-out";
        }, 300);
    }
    
    // 配置表单提交事件监听
    var configForm = document.getElementById("config-form");
    if (configForm) {
        configForm.addEventListener("submit", function () {
            console.log("表单提交");
            // 显示提交前的值
            const dbHost = document.getElementById("db_host").value;
            const dbDatabase = document.getElementById("db_database").value;
            console.log("数据库主机:", dbHost);
            console.log("数据库名称:", dbDatabase);
        });
    }

    // 如果存在安装日志区域，确保它是可见的
    var installLog = document.getElementById("install-log-div");
    if (installLog && window.location.search.includes("install=1")) {
        installLog.style.display = "block";

        // 监视DOM变化，确保新添加的元素样式正确
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === "childList") {
                    // 对所有新添加的成功消息应用样式
                    var successElements =
                        installLog.querySelectorAll(".success");
                    successElements.forEach(function (element) {
                        if (element.tagName === "DIV") {
                            element.style.color = "#4CAF50";
                            element.style.borderLeft = "4px solid #4CAF50";
                            element.style.padding = "5px 10px";
                            element.style.margin = "5px 0";
                            element.style.display = "block";
                            element.style.background = "transparent";
                        }
                    });

                    // 对所有新添加的警告消息应用样式
                    var warningElements =
                        installLog.querySelectorAll(".warning");
                    warningElements.forEach(function (element) {
                        if (element.tagName === "DIV") {
                            element.style.color = "#FFC107";
                            element.style.borderLeft = "4px solid #FFC107";
                            element.style.padding = "5px 10px";
                            element.style.margin = "5px 0";
                            element.style.display = "block";
                            element.style.background = "transparent";
                        }
                    });

                    // 对所有新添加的错误消息应用样式
                    var errorElements = installLog.querySelectorAll(".error");
                    errorElements.forEach(function (element) {
                        if (element.tagName === "DIV") {
                            element.style.color = "#F44336";
                            element.style.borderLeft = "4px solid #F44336";
                            element.style.padding = "5px 10px";
                            element.style.margin = "5px 0";
                            element.style.display = "block";
                            element.style.background = "transparent";
                        }
                    });
                }
            });
        });

        // 配置和启动监视器
        observer.observe(installLog, { childList: true, subtree: true });
    }

    // 格式化输出中的pre标签内容，确保转义字符正确显示
    document.querySelectorAll("#install-log-div pre").forEach(function (pre) {
        pre.textContent = decodeHtmlEntities(pre.innerHTML);
    });
});
