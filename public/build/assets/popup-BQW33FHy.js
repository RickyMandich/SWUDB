const n=document.getElementsByTagName("head")[0],o=document.createElement("link");o.rel="stylesheet";o.type="text/css";o.href="https://cdn.jsdelivr.net/npm/@simondmc/popup-js@1.4.3/popup.min.css";o.media="all";n.appendChild(o);class r{constructor(e={}){this.params=e,this.init()}init(){this.id=this.params.id??"popup",this.title=this.params.title??"Popup Title",this.content=this.params.content??"Popup Content",this.titleColor=this.params.titleColor??"#ffffff",this.backgroundColor=this.params.backgroundColor??"#555555",this.closeColor=this.params.closeColor??"#ffffff",this.textColor=this.params.textColor??"#ffffff",this.linkColor=this.params.linkColor??"#383838",this.widthMultiplier=this.params.widthMultiplier??1,this.heightMultiplier=this.params.heightMultiplier??.66,this.fontSizeMultiplier=this.params.fontSizeMultiplier??1,this.borderRadius=this.params.borderRadius??"15px",this.sideMargin=this.params.sideMargin??"3%",this.titleMargin=this.params.titleMargin??"2%",this.lineSpacing=this.params.lineSpacing??"auto",this.showImmediately=this.params.showImmediately??!1,this.showOnce=this.params.showOnce??!1,this.fixedHeight=this.params.fixedHeight??!1,this.allowClose=this.params.allowClose??!0,this.underlineLinks=this.params.underlineLinks??!1,this.fadeTime=this.params.fadeTime??"0.3s",this.buttonWidth=this.params.buttonWidth??"fit-content",this.borderWidth=this.params.borderWidth??"0",this.borderColor=this.params.borderColor??"#000000",this.disableScroll=this.params.disableScroll??!0,this.textShadow=this.params.textShadow??"none",this.hideCloseButton=this.params.hideCloseButton??!1,this.hideTitle=this.params.hideTitle??!1,this.height=`min(${770*this.heightMultiplier}px, ${90*this.heightMultiplier}vw)`,this.width=`min(${770*this.widthMultiplier}px, ${90*this.widthMultiplier}vw)`,this.fontSize=`min(${25*this.fontSizeMultiplier}px, ${4*this.fontSizeMultiplier}vw)`,this.css=this.params.css??"",this.css+=`
        .popup.${this.id} {
            transition-duration: ${this.fadeTime};
            text-shadow: ${this.textShadow};
            font-family: '${this.params.font??"Inter"}', 'Inter', Helvetica, sans-serif;
        }
        
        .popup.${this.id} .popup-content {
            background-color: ${this.backgroundColor};
            width: ${this.width}; 
            height: ${this.fixedHeight?this.height:"unset"};
            border-radius: ${this.borderRadius};
            border: ${this.borderWidth} solid ${this.borderColor};
        }

        .popup.${this.id} .popup-header {
            margin-bottom: ${this.titleMargin};
        }

        .popup.${this.id} .popup-title {
            color: ${this.titleColor};
        }

        .popup.${this.id} .popup-close {
            color: ${this.closeColor};
        }

        .popup.${this.id} .popup-body {
            color: ${this.textColor};
            margin-left: ${this.sideMargin};
            margin-right: ${this.sideMargin};
            line-height: ${this.lineSpacing};
            font-size: ${this.fontSize};
        }

        .popup.${this.id} .popup-body button { 
            width: ${this.buttonWidth}; 
        }

        .popup.${this.id} .popup-body a { 
            color: ${this.linkColor};
            ${this.underlineLinks?"text-decoration: underline;":""}
        }`;const e=document.head,l=document.createElement("style");e.append(l),l.appendChild(document.createTextNode(this.css)),this.content=this.content.split(`
`);for(let i=0;i<this.content.length;i++){let t=this.content[i].trim();if(t!==""){if(t.includes("§")){const a=t.split("§");t=`<p class="${a[0].trim()}">${a[1].trim()}</p>`}else t=`<p>${t}</p>`;for(t=t.replace(/  /g,"&nbsp;&nbsp;");/{a-(.*?)}\[(.*?)]/.test(t);)t=t.replace(/{a-(.*?)}\[(.*?)]/g,'<a href="$1" target="_blank">$2</a>');for(;/{btn-(.*?)}\[(.*?)]/.test(t);)t=t.replace(/{btn-(.*?)}\[(.*?)]/g,'<button class="$1">$2</button>');t=t.replace(/([^\\]?){/g,'$1<span class="').replace(/([^\\]?)}\[/g,'$1">').replace(/([^\\]?)]/g,"$1</span>"),this.content[i]=t}}if(this.content=this.content.join(""),this.popupEl=document.createElement("div"),this.popupEl.classList.add("popup"),this.popupEl.classList.add(this.id),this.popupEl.innerHTML=`
        <div class="popup-content">
            <div class="popup-header">
                ${this.hideTitle?"":`<div class="popup-title">${this.title}</div>`}
                ${this.allowClose&&!this.hideCloseButton?'<div class="popup-close">&times;</div>':""}
            </div>
            <div class="popup-body">${this.content}</div>
        </div>`,document.body.appendChild(this.popupEl),this.popupEl.addEventListener("click",i=>{if(i.target.className=="popup-close"||i.target.classList.contains("popup")){if(!this.allowClose)return;this.hide()}}),this.params.loadCallback&&typeof this.params.loadCallback=="function"&&this.params.loadCallback(),this.showImmediately){if(this.showOnce&&localStorage&&localStorage.getItem("popup-"+this.id))return;this.popupEl.classList.add("fade-in"),h(p)}document.addEventListener("keydown",i=>{if(i.key==="Escape"){if(!this.allowClose)return;this.hide()}})}show(){this.popupEl.classList.remove("fade-out"),this.popupEl.classList.add("fade-in"),h(this.params.disableScroll??!0)}hide(){this.popupEl.classList.remove("fade-in"),this.popupEl.classList.add("fade-out"),localStorage&&this.showOnce&&localStorage.setItem("popup-"+this.id,!0),d(this)}}function h(s){s&&p()}function d(s){s.params.hideCallback&&typeof s.params.hideCallback=="function"&&s.params.hideCallback(),c()}function p(){const s=window.scrollY||document.documentElement.scrollTop,e=window.scrollX||document.documentElement.scrollLeft;window.onscroll=function(){window.scrollTo(e,s)}}function c(){window.onscroll=function(){}}window.Popup=r;
