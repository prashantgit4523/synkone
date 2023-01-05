import React, { forwardRef, Fragment, useEffect, useImperativeHandle, useState } from 'react';
import { MultiSelect } from "react-multi-select-component";
import { useDispatch, useSelector } from 'react-redux';
import { fetchProjectFilterData, updateSelectedProjects } from '../../../store/actions/global-dashboard/project-filter';
import { useDidMountEffect } from '../../../custom-hooks';

function ProjectFilter(props, ref) {
    const dispatch = useDispatch()
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value)
    const { selectedDepartment } = useSelector(state => state.commonReducer.departmentFilterReducer)
    const { projects } = useSelector(state => state.globalDashboardReducer.projectFilterReducer)
    const [selected, setSelected] = useState([]);
    const [projectOptions, setProjectOptions] = useState([])
    const [isLoaded, setIsLoaded] = useState(false);


    useEffect(() => {
        // var project_array=project_f.split(',');
        // var project_array=props.selectedProjects.map(function (obj) { return obj.value; });
        
        // var selected_data = projects.filter((project) => {
        //     return project_array.includes(project.value.toString())
        // })
        // if (props.selectedProjects){
        //     setSelected(props.selectedProjects)
        // }
    }, [appDataScope])

    /* Fires when selectedDepartment updates */
    useDidMountEffect(() => {
        dispatch(fetchProjectFilterData({
            data_scope: appDataScope,
            selected_departments: selectedDepartment.join()
        }))
    }, [selectedDepartment])

    /* Setting project filter options */
    useDidMountEffect(() => {
        /* Un-selecting prev-selected before re-render */
        // unSelectAll()
        let data = projects.map((project) => {
            return {
                value: project.id,
                label: project.name
            }
        })
        if(data.length>0){
            setProjectOptions(data)
        }
        else{
            setProjectOptions([])
            setSelected([]);
        }

        var project_array=props.selectedProjects.map(function (obj) { return obj.value; });
        
        var selected_data = data.filter((project) => {
            return project_array.includes(project.value)
        });
        if(selected_data.length>0){
            setSelected(selected_data);
        }
        else{
            if(!props.is_first){
                setSelected(data);
            }
            else{
                setSelected([]);
            }
        }
        props.actionFunction(selected);
        props.reloadFunction();
        // if (props.selectedProjects){
        //     setSelected(props.selectedProjects)
        // }

    }, [projects])

    /* when project options are updated on data scope change */
    useDidMountEffect(() => {
        if(isLoaded){
            selectAll();
        }
        // selectAll()
    }, [projectOptions])


    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
        selectAll,
        toggleIsLoaded
    }));

    /* Handling project select*/
    const handleProjectSelect = (selectedProjects) => {
        /*updating local state*/
        setSelected(selectedProjects)
        props.actionFunction(selectedProjects);
    }

    /* Selects the all options */
    const selectAll = () => {
        setSelected(projectOptions)
    }

    const toggleIsLoaded = () => {
        setIsLoaded(true);
    }

    /* Un-select all*/
    const unSelectAll = () => {
        setSelected([])
    }

    /* updateSelectedProjects in Store */
    useDidMountEffect(() => {
        dispatch(updateSelectedProjects(selected.map(selectedProject => selectedProject.value)))
    }, [selected])


    return (
        <Fragment>
            <MultiSelect
                options={projectOptions}
                value={selected}
                onChange={handleProjectSelect}
                labelledBy="Select"
                overrideStrings={{
                    allItemsAreSelected: "All projects are selected",
                }}
            />
        </Fragment>
    );
}

export default forwardRef(ProjectFilter);
