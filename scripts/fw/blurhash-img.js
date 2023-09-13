var __decorate = this && this.__decorate || function (decorators, target, key, desc) {
  var c = arguments.length,r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc,d;
  if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);else
  for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
  return c > 3 && r && Object.defineProperty(target, key, r), r;
};
import { html, css, customElement, LitElement, property, query } from "https://unpkg.com/lit-element@^2.3.1?module";
import { decode } from "https://unpkg.com/@fpapado/blurhash@^1.1.4?module";
let BlurhashImg = class BlurhashImg extends LitElement {
  constructor() {
    super(...arguments);
    /**
                          * The X-axis resolution in which the decoded image will be rendered at. Recommended min. 32px. Large sizes (>128px) will greatly decrease rendering performance.
                          */
    this.resolutionX = 32;
    /**
                            * The Y-axis resolution in which the decoded image will be rendered at. Recommended min. 32px. Large sizes (>128px) will greatly decrease rendering performance.
                            */
    this.resolutionY = 32;
  }
  __updateCanvasImage() {
    if (this.hash) {
      try {
        const pixels = decode(this.hash, this.resolutionX, this.resolutionY);
        // Set the pixels to the canvas
        const imageData = new ImageData(pixels, this.resolutionX, this.resolutionY);
        const canvasEl = this.canvas;
        if (canvasEl) {
          const ctx = canvasEl.getContext('2d');
          if (ctx) {
            ctx.putImageData(imageData, 0, 0);
          }
        }
      }
      catch (error) {
        console.log(error);
      }
    }
  }
  firstUpdated() {
    this.__updateCanvasImage();
  }
  updated(changedProperties) {
    if (changedProperties.get('hash') ||
    changedProperties.get('resolutionX') ||
    changedProperties.get('resolutionY')) {
      this.__updateCanvasImage();
    }
  }
  render() {
    return html`
      <div class="wrapper">
        <canvas
          width="${this.resolutionX}"
          height="${this.resolutionY}"
        ></canvas>
      </div>
    `;
  }};

/* Layout notes:
       * Images keep their aspect ratio after they are replaced.
       * To make the layout of this component analogous, we use an
       * --aspect-ratio CSS Custom Property.
       */
BlurhashImg.styles = css`
    :host {
      display: block;
      max-width: 100%;
    }

    .wrapper {
      position: relative;
      height: 0;
      padding-bottom: calc(var(--aspect-ratio) * 100%);
    }

    canvas {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }
  `;
__decorate([
property({ type: String })],
BlurhashImg.prototype, "hash", void 0);
__decorate([
property({ type: Number })],
BlurhashImg.prototype, "resolutionX", void 0);
__decorate([
property({ type: Number })],
BlurhashImg.prototype, "resolutionY", void 0);
__decorate([
query('canvas')],
BlurhashImg.prototype, "canvas", void 0);
BlurhashImg = __decorate([
customElement('blurhash-img')],
BlurhashImg);
export { BlurhashImg };