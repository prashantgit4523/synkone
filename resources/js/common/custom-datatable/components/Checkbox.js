import React from 'react';

const Checkbox = ({id, checked, onClick, ...rest}) => {
    return (
        <div className="checkbox checkbox-success cursor-pointer">
            <input
                type="checkbox"
                onChange={onClick}
                checked={checked}
                id={id}
                {...rest}
            />
            <label htmlFor={id}/>
        </div>
    );
}

export default Checkbox;