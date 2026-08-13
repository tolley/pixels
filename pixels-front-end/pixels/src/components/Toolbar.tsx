// import React from 'react';

export const Toolbar = () => {
    return (
        <div id="toolbar">
            <h1>Pixels</h1>

            <div id="active-color-wrap" title="Select a color">
                <div id="active-color"></div>
                <input type="color" id="color-picker" />
            </div>

            <button type="button" id="btn-eraser" title="Erase pixel (remove colour)">✕</button>

            <div className="toolbar-actions">
                <button type="button" id="btn-submit" title="Submit pixels for review for inclusion" disabled>Submit</button>
                <button type="button" id="btn-reset" title="Clear non submitted pixels" disabled>Reset</button>
            </div>

            <div id="user-info">
                <span>UsErNaMe!</span>
                <a id="toggle-email-allowed" title="Enable/Disable emails">@</a>
                <a href="/image?x1=1&y1=1&x2=400&y2=400&scale=2" title="See the GIF version of this grid." target="_blank">GIF</a>

                <a href="review" title="Review Submissions">Review</a>
                
                <a href="/logout" title="Logout">Logout</a>
            </div>
        </div>
    );
}

export default Toolbar;