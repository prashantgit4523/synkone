import React, {useRef, useState} from 'react';
import {Overlay, Popover} from "react-bootstrap";

import './style.css';

const CustomDropdown = ({button, dropdownItems}) => {
    const [show, setShow] = useState(false);
    const containerRef = useRef(null);

    const onHide = () => setShow(false);

    return (
        <div ref={containerRef} className="d-inline-block" onClick={() => setShow(!show)}>
            <span className="cursor-pointer">{button}</span>
            <Overlay
                show={show}
                target={containerRef}
                onHide={onHide}
                placement="bottom-end"
                popperConfig={{
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 0],
                            },
                        },
                    ]
                }}
                rootClose
            >
                <Popover onClick={onHide}>
                    <div className="custom-dropdown">
                        {dropdownItems}
                    </div>
                </Popover>
            </Overlay>
        </div>
    )
};

export default CustomDropdown;