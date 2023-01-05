import React, { forwardRef, Fragment, useRef, useImperativeHandle, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchStandardFilterData, updateSelectedStandards } from '../../../../store/actions/controls/standardFilter';
import { useDidMountEffect } from '../../../../custom-hooks';
import Select from '../../../../common/custom-react-select/CustomReactSelect';

import './project-filter.css';

function StandardFilter(props, ref) {
    const selectRolesRef = useRef();
    const dispatch = useDispatch()
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value)
    const { selectedDepartment } = useSelector(state => state.commonReducer.departmentFilterReducer)
    const { standards } = useSelector(state => state.controlReducer.standardFilterReducer)
    const [selected, setSelected] = useState([]);
    const [standardOptions, setStandardOptions] = useState([])

    /* Fires when selectedDepartment updates */
    useDidMountEffect(() => {
        dispatch(fetchStandardFilterData({
            data_scope: appDataScope,
            selected_departments: selectedDepartment.join()
        }))
    }, [selectedDepartment])


    /* Setting project filter options */
    useDidMountEffect(() => {
        /* Un-selecting prev-selected before re-render */
        // unSelectAll()

        let data = standards.map((project) => {
            return {
                value: project.id,
                label: project.name
            }
        })

        setStandardOptions(data)
    }, [standards])

    /* when project options are updated on data scope change */
    useDidMountEffect(() => {
        // selectAll()
        selectRolesRef.current.clearValue();
        if (standardOptions.length > 0) {
            selectRolesRef.current.setValue(standardOptions[0]);
        } else {
            selectRolesRef.current.setValue([]);
        }
    }, [standardOptions])

    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
        selectAll
    }));

    /* Handling project select*/
    const handleProjectSelect = (selectedProjects) => {
        /*updating local state*/
        if (selectedProjects) {
            setSelected(selectedProjects)
        }
    }

    /* Selects the all options */
    const selectAll = () => {
        setSelected(standardOptions)
    }

    /* Un-select all*/
    const unSelectAll = () => {
        setSelected([])
    }

    /* updateSelectedProjects in Store */
    useDidMountEffect(() => {
        if (selected) {
            dispatch(updateSelectedStandards([selected.value]))
        }
    }, [selected])

    const styles = {
        control: (css) => ({
          ...css,
          width: 200
        }),
        menu: ({ width, ...css }) => ({
          ...css,
          width: "max-content",
          minWidth: "100%"
        }),
        // Add padding to account for width of Indicators Container plus padding
        option: (css) => ({ ...css, width: 200 })
      };

    return (
        <Fragment>
            <Select
                className="react-select"
                classNamePrefix="react-select"
                ref={selectRolesRef}
                onChange={handleProjectSelect}
                options={standardOptions}
                defaultValue={selected}
                placeholder="Select standard(s)"
                styles={styles}
            />
            {/* <MultiSelect
                options={standardOptions}
                value={selected}
                onChange={handleProjectSelect}
                className="w-100 project-filter-container"
                overrideStrings={{
                    allItemsAreSelected: "All standard are selected",
                    search: "Search standards(s)",
                    "selectSomeItems": "Select standards(s)",
                }}
                labelledBy="Select standards(s)"
            /> */}
        </Fragment>
    );
}

export default forwardRef(StandardFilter);
