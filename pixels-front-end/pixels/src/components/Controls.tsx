
export const Controls = () => {
    return (
        <div id="side">
        <div className="panel-section">
            <span className="panel-label">Zoom</span>
            <div id="zoom-controls">
                <button type="button" id="btn-zoom-out">−</button>
                <span className="panel-value" id="zoom-display">4 px</span>
                <button type="button" id="btn-zoom-in">+</button>
            </div>
            <br />

            <span className="panel-label">Step size</span>
            <select id="step-select">
                <option value="1">1 cell</option>
                <option value="5">5 cells</option>
                <option value="10">10 cells</option>
                <option value="25" selected>25 cells</option>
                <option value="50">50 cells</option>
                <option value="100">100 cells</option>
            </select>
        </div>

        <div className="panel-section">
            <span className="panel-label">Navigate</span>
            <div id="nav-pad">
                <button type="button" id="btn-nav-up">▲</button>
                <button type="button" id="btn-nav-left">◀</button>
                <button type="button" id="btn-nav-right">▶</button>
                <button type="button" id="btn-nav-down">▼</button>
            </div>
        </div>

        <div className="panel-section">
            <span className="panel-label">
                Position
            </span>
            <span className="panel-value" id="coords">x: 0  y: 0</span>
            <br />

            <span className="panel-label">Visible cells</span>
            <span className="panel-value" id="vp-display">— × —</span>
        </div>
        {/* <div id="ad-slot">
            <ins className="adsbygoogle"
                 style={ { display: 'block' } }
                 data-ad-client="ca-pub-483307165"
                 data-ad-slot="auto"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
        </div> */}
    </div>
    );
}