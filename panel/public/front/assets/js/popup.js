let popup = ({ img, text }) => {
  const random = Math.floor(Math.random() * 100);

  let closeButton = document.createElement("button");
  closeButton.classList.add("close");
  closeButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="17.556" height="17.556" viewBox="0 0 17.556 17.556"><g id="Group_2815" data-name="Group 2815" transform="translate(-358.858 -128.414)"><path id="Path_17633" data-name="Path 17633" d="M-111.191,128.767l-14.728,14.728" transform="translate(486.191 1.061)" fill="none" stroke="#afafaf" stroke-linecap="round" stroke-width="2"/><path id="Path_17634" data-name="Path 17634" d="M14.728,0,0,14.728" transform="translate(375 129.828) rotate(90)" fill="none" stroke="#afafaf" stroke-linecap="round" stroke-width="2"/></g></svg>`;

  let popupBlock = document.createElement("div");
  popupBlock.classList.add("popup");

  let popupMain = document.createElement("div");
  popupMain.classList.add("main");
  popupMain.classList.add("main-" + random);

  if (img !== undefined) {
    popupMain.classList.add("img-view");

    let popupImg = document.createElement("img");
    popupImg.classList.add("popup-img");
    popupImg.setAttribute("src", img.src);
    popupImg.setAttribute("alt", img.alt !== undefined ? img.alt : "");

    if (img.url !== undefined) {
      let popupRedirect = document.createElement("a");
      popupRedirect.setAttribute("href", img.url);
      popupRedirect.setAttribute("alt", img.alt !== undefined ? img.alt : "");
      popupRedirect.setAttribute(
        "target",
        img.target !== undefined ? img.target : ""
      );

      popupRedirect.append(popupImg);
      popupMain.append(closeButton, popupRedirect);
    } else {
      popupMain.append(closeButton, popupImg);
    }
  } else {
    let popupDetail = document.createElement("div");
    popupDetail.classList.add("detail");
    popupDetail.classList.add(text.class ?? 'a');
    popupDetail.innerHTML = text.body;

    let popupHead = document.createElement("h2");
    popupHead.innerHTML = text.head;

    let button = null

    if(text.button !== undefined){
        button = document.createElement('button')
        button.innerHTML = text.button.name
        button.classList.add('submit-button')
        if(text.button.class === undefined)  button.classList.add(text.button.class)

        button.addEventListener('click', text.button.proccess)
    }

    popupMain.append(closeButton, popupHead, popupDetail, button ?? '');
    document.querySelector('.main').classList.add('hide')
  }

  closeButton.addEventListener("click", (event) => {
    event.preventDefault();
    popupBlock.classList.add("remove");
    document.querySelector('.main').classList.remove('hide')
    setTimeout(() => {
      popupBlock.remove();
    }, 500);
  });

  popupBlock.appendChild(popupMain);

  document.body.appendChild(popupBlock);

};