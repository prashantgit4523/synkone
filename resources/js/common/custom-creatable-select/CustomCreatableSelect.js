import { useState, useEffect } from 'react';
import CreatableSelect from 'react-select/creatable';

const CustomCreatableSelect = ({ selectOptions, selectValue, onChangeHandler, onInputChangeHandler }) => {

    const [options, setOptions] = useState(selectOptions);
    const [value, setValue] = useState();
    const [inputValue, setInputValue] = useState();
    
    useEffect(() => {
        setValue(selectValue);
      }, [selectValue]);
      
    const handleBlur = () => {
        const label = inputValue?.trim() || "";
        const optionExists = options.find((opt) => opt.label === label);

        if (!label || optionExists) {
            return;
        }

        const option = { label, value: label };

        // Add new option to menu list
        setOptions([...options, option]);
        // Add value to selected options
        setValue([...(value || []), option]);
        // Update selected core value
        onInputChangeHandler([...(value || []), option]);
        // Clear input value
        setInputValue("");
    };

    const onInputChange = (textInput, { action }) => {
        if (action === "input-change") {
            setInputValue(textInput);
        }
        if (action === "input-blur") {
            handleBlur();
        }
    };

    const handleChange = (selected) => {
        setValue(selected);
        setInputValue("");
    };

    return (
        <CreatableSelect
            isMulti
            value={value}
            inputValue={inputValue}
            onChange={(val) => {
                onChangeHandler(val);
                handleChange(val);
            }
            }
            options={options}
            onInputChange={onInputChange}
        />
    )

}

export default CustomCreatableSelect;