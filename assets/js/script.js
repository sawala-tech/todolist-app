document.addEventListener("DOMContentLoaded", () => {
  console.log("DOM fully loaded and parsed")
  handleSplashScreen()
  handleShowHidePassword()
  handleEditAttachmentIntent()
  handleModal("addTodo")
  handleModal("editTodo", [
    "id",
    "title",
    "description",
    "deadline",
    "attachment",
    "status",
  ])
  handleModal("deleteTodo", [
    "id",
    "title",
    "description",
    "deadline",
    "attachment",
    "status",
  ])
  handleDeleteTask()

  if (!isDesktop()) {
    toggleDropdown()
    toggleFilterTodo()
  }
})

function handleSplashScreen() {
  document.body.style.visibility = "visible"
  document.body.style.opacity = "1"
}

function handleShowHidePassword() {
  const cta = document.getElementById("cta-show-hide-password")
  const passwordField = document.getElementById("password")
  const openEye = document.getElementById("open-eye")
  const closeEye = document.getElementById("close-eye")

  if (!cta || !passwordField || !openEye || !closeEye) return

  cta.addEventListener("click", () => {
    const isPassword = passwordField.type === "password"
    passwordField.type = isPassword ? "text" : "password"
    openEye.style.display = isPassword ? "block" : "none"
    closeEye.style.display = isPassword ? "none" : "block"
  })
}

function handleEditAttachmentIntent() {
  const editModal = document.getElementById("editTodoModal")
  if (!editModal) return

  const fileInput = editModal.querySelector('input[name="attachment"]')
  const replaceInput = editModal.querySelector(
    'input[name="replace_attachment"]',
  )

  if (!fileInput || !replaceInput) return

  fileInput.addEventListener("change", () => {
    replaceInput.value =
      fileInput.files && fileInput.files.length > 0 ? "1" : "0"
  })
}

function getAppBasePath() {
  const segments = window.location.pathname.split("/").filter(Boolean)
  return segments.length ? `/${segments[0]}` : ""
}

function getPublicAttachmentUrl(fileName) {
  const cleanFileName = (fileName || "").trim()
  return `${window.location.origin}${getAppBasePath()}/assets/public/${cleanFileName}`
}

function handleModal(type, dataAttributes = []) {
  const triggers = document.querySelectorAll(`#${type}Trigger`)
  const modal = document.getElementById(`${type}Modal`)
  const closeModal = document.getElementById(`close${capitalize(type)}Modal`)

  if (!triggers.length || !modal || !closeModal) return

  triggers.forEach((trigger) => {
    trigger.addEventListener("click", () => {
      if (dataAttributes.length) {
        dataAttributes.forEach((attr) => {
          if (type === "deleteTodo") {
            const confirmDeleteTodoTrigger = document.getElementById(
              "confirmDeleteTodoTrigger",
            )
            if (confirmDeleteTodoTrigger) {
              confirmDeleteTodoTrigger.setAttribute(
                "data-id",
                trigger.dataset.id || "",
              )
            }

            const element = document.getElementById(`modal-${attr}`)
            if (element) {
              let value = (trigger.dataset[attr] || "").trim()
              if (attr === "deadline") {
                if (value) {
                  const parsedDate = new Date(value)
                  if (!Number.isNaN(parsedDate.getTime())) {
                    const date = parsedDate
                      .toLocaleDateString("en-GB", {
                        day: "2-digit",
                        month: "short",
                        year: "numeric",
                      })
                      .replace(/ /g, " ")
                    value = `Tenggat Waktu: ${date}`
                  } else {
                    value = "Tenggat Waktu: -"
                  }
                } else {
                  value = "Tenggat Waktu: -"
                }
              }
              if (element.tagName === "A") {
                if (value) {
                  element.setAttribute("href", getPublicAttachmentUrl(value))
                  element.textContent = value.split("/").pop()
                } else {
                  element.setAttribute("href", "#")
                  element.textContent = "-"
                }
              } else {
                element.textContent = value || "-"
              }
            }
          } else if (type === "editTodo") {
            modal
              .querySelectorAll("input, textarea, select")
              .forEach(async (input) => {
                const name = input.name
                const value = trigger.dataset[name]
                if (input.name === "deadline") {
                  input.value = new Date(value).toISOString().split("T")[0]
                } else if (input.name === "attachment") {
                  // Keep file input empty on edit; existing attachment is retained server-side unless user selects a new file.
                  input.value = ""
                  const replaceInput = modal.querySelector(
                    'input[name="replace_attachment"]',
                  )
                  if (replaceInput) replaceInput.value = "0"

                  const currentAttachmentLink = modal.querySelector(
                    "#editCurrentAttachment",
                  )
                  if (currentAttachmentLink) {
                    const attachmentName = (value || "").trim()
                    if (attachmentName) {
                      currentAttachmentLink.setAttribute(
                        "href",
                        getPublicAttachmentUrl(attachmentName),
                      )
                      currentAttachmentLink.textContent = attachmentName
                        .split("/")
                        .pop()
                    } else {
                      currentAttachmentLink.setAttribute("href", "#")
                      currentAttachmentLink.textContent = "-"
                    }
                  }
                } else if (input.name === "_method") {
                  input.value = "PUT"
                } else if (input.name === "status") {
                  input.value = value
                } else {
                  input.value = value
                }
              })
          } else {
            return
          }
        })
      }
      toggleModal(modal, true)
    })
  })

  closeModal.addEventListener("click", () => toggleModal(modal, false))
  modal.addEventListener(
    "click",
    (e) => e.target === modal && toggleModal(modal, false),
  )

  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.querySelectorAll("input, textarea").forEach((input) => {
        input.value = ""
      })
    }
  })
}

function toggleModal(modal, show) {
  modal.classList.toggle("hidden", !show)
  modal.classList.toggle("flex", show)
  document.body.style.overflow = show ? "hidden" : "auto"
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function toggleDropdown() {
  const dropdownTrigger = document.getElementById("dropdownTrigger")
  const dropdownMenu = document.getElementById("dropdownMenu")

  if (!dropdownTrigger || !dropdownMenu) return

  dropdownTrigger.addEventListener("click", () => {
    dropdownMenu.classList.toggle("hidden")
  })

  document.addEventListener("click", (e) => {
    if (
      !dropdownMenu.contains(e.target) &&
      !dropdownTrigger.contains(e.target)
    ) {
      dropdownMenu.classList.add("hidden")
    }
  })
}

function toggleFilterTodo() {
  const filterTodoTriggers = document.querySelectorAll("#filterTodoTrigger")
  const todoListWrappers = document.querySelectorAll("#todoListWrapper")

  if (!filterTodoTriggers.length || !todoListWrappers.length) return

  const defaultCategory = "open"

  filterTodoTriggers.forEach((trigger) => {
    const type = trigger.getAttribute("data-type")

    if (type === defaultCategory) {
      trigger.classList.remove("!bg-[#E2E8F0]")
    } else {
      trigger.classList.add("!bg-[#E2E8F0]")
    }
  })

  todoListWrappers.forEach((wrapper) => {
    wrapper.style.display =
      wrapper.getAttribute("data-type") === defaultCategory ? "block" : "none"
  })

  filterTodoTriggers.forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const selectedType = trigger.getAttribute("data-type")

      todoListWrappers.forEach((wrapper) => {
        wrapper.style.display =
          wrapper.getAttribute("data-type") === selectedType ? "block" : "none"
      })

      filterTodoTriggers.forEach((t) => t.classList.add("!bg-[#E2E8F0]"))
      trigger.classList.remove("!bg-[#E2E8F0]")
    })
  })
}

function handleDeleteTask() {
  const confirmDeleteTodoTrigger = document.getElementById(
    "confirmDeleteTodoTrigger",
  )
  if (!confirmDeleteTodoTrigger) return

  confirmDeleteTodoTrigger.addEventListener("click", async () => {
    const id = confirmDeleteTodoTrigger.getAttribute("data-id")
    if (!id) return

    try {
      const response = await fetch(`${window.location.href}?id=${id}`, {
        method: "DELETE",
      })

      if (!response.ok) {
        throw new Error("Delete request failed")
      }

      window.location.reload()
    } catch (error) {
      console.error(error)
      Swal.fire({
        icon: "error",
        title: "Gagal menghapus tugas",
      })
    }
  })
}

function isDesktop() {
  window.addEventListener("resize", () => {
    location.reload()
  })

  return window.innerWidth >= 1024
}
