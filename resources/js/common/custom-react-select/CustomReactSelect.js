import React from "react";
import Select from "react-select";
import {usePage} from "@inertiajs/inertia-react";

const CustomReactSelect = (props, ref) => {
    const {globalSetting: {secondary_color}} = usePage().props;
    return (
        <Select
            ref={ref}
            theme={(theme) => ({
                ...theme,
                colors: {
                    ...theme.colors,
                    primary: secondary_color
                },
            })}
            {...props}
        />
    );
}

export default React.forwardRef(CustomReactSelect);